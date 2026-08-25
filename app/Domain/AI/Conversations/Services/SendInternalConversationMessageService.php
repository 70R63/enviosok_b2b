<?php

namespace App\Domain\AI\Conversations\Services;

use App\Domain\AI\Agents\Data\AuthorizedAiLifecycleActor;
use App\Domain\AI\Agents\Enums\AgentVersionStatus;
use App\Domain\AI\Agents\Models\Agent;
use App\Domain\AI\Agents\Models\AgentVersion;
use App\Domain\AI\Agents\Services\AiLifecycleAuthorization;
use App\Domain\AI\Conversations\Data\ConversationTurnResult;
use App\Domain\AI\Conversations\Data\SendConversationMessageData;
use App\Domain\AI\Conversations\Enums\ConversationMessageRole;
use App\Domain\AI\Conversations\Enums\ConversationMessageStatus;
use App\Domain\AI\Conversations\Enums\ConversationChannel;
use App\Domain\AI\Conversations\Exceptions\ConversationTurnBusyException;
use App\Domain\AI\Conversations\Models\Conversation;
use App\Domain\AI\Conversations\Models\ConversationMessage;
use App\Domain\AI\Conversations\Models\ConversationMessageCitation;
use App\Domain\AI\Handoff\Services\RequestHumanHandoffService;
use App\Domain\AI\Leads\Services\RecordLeadOutcomeCandidatesService;
use App\Domain\AI\Runtime\Models\RuntimeRun;
use App\Domain\AI\Runtime\Services\GenerateAgentDraftResponseService;
use App\Domain\AI\Tenancy\AiTenantBoundary;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Domain\AI\Actions\Enums\ActionRunStatus;
use App\Domain\AI\Actions\Services\{ActionExecutor,GeneratePostActionResponseService};

final class SendInternalConversationMessageService
{
    public function __construct(private AiLifecycleAuthorization $auth, private AiTenantBoundary $tenants, private GenerateAgentDraftResponseService $runtime, private RecordLeadOutcomeCandidatesService $outcomes, private RequestHumanHandoffService $handoffs, private ActionExecutor $actions, private GeneratePostActionResponseService $postAction) {}

    public function send(User $actor, Conversation $conversation, SendConversationMessageData $data, ?callable $beforeReserve = null): ConversationTurnResult
    {
        $authorized = $this->auth->authorize($actor);
        $this->tenants->assertResourceBelongsToCurrentTenant($conversation);
        [$user,$assistant,$agent,$version,$history] = DB::transaction(function () use ($authorized, $conversation, $data, $beforeReserve) {
            if ($beforeReserve) {
                $beforeReserve();
            }
            $c = Conversation::query()->lockForUpdate()->findOrFail($conversation->id);
            $authorized = $this->auth->revalidate($authorized);
            $this->recoverAbandonedTurn($c, $authorized);
            $agent = Agent::query()->whereKey($c->agent_id)->lockForUpdate()->firstOrFail();
            $version = AgentVersion::query()->where('agent_id', $agent->id)->whereKey($c->agent_version_id)->lockForUpdate()->firstOrFail();
            $allowed = in_array($c->channel, [ConversationChannel::Webchat, ConversationChannel::WhatsApp], true)
                ? [AgentVersionStatus::Published, AgentVersionStatus::Retired]
                : [AgentVersionStatus::Draft, AgentVersionStatus::Testing];
            if (! in_array($version->status, $allowed, true)) {
                throw new \DomainException('The pinned Agent Version is no longer executable.');
            }$history = $c->messages()->where('status', ConversationMessageStatus::Completed->value)->orderByDesc('sequence')->limit(6)->get()->reverse()->map(fn ($m) => ['role' => $m->role->value, 'content' => $m->content])->values()->all();
            [$userSequence,$assistantSequence] = $c->reserveTurn($authorized);
            $user = new ConversationMessage;
            $user->conversation_id = $c->id;
            $user->sequence = $userSequence;
            $user->role = ConversationMessageRole::User;
            $user->status = ConversationMessageStatus::Completed;
            $user->content = $data->message->value;
            $user->completed_at = now();
            $user->save();
            $assistant = new ConversationMessage;
            $assistant->conversation_id = $c->id;
            $assistant->sequence = $assistantSequence;
            $assistant->role = ConversationMessageRole::Assistant;
            $assistant->status = ConversationMessageStatus::Pending;
            $assistant->content = null;
            $assistant->needs_handoff = false;
            $assistant->save();

            return [$user, $assistant, $agent, $version, $history];
        });
        try {
            $result = $this->runtime->generate($actor, $agent, $data->message, $version, $history);
            if ($result->actionRequest) {
                $run = RuntimeRun::query()->findOrFail((int) $result->runtimeRunId);
                $user = DB::transaction(function () use ($authorized, $conversation, $user, $run) {
                    Conversation::query()->lockForUpdate()->findOrFail($conversation->id);
                    $message = ConversationMessage::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                    $message->linkSourceRuntimeRun($this->auth->revalidate($authorized), $run->id);

                    return $message->fresh();
                });
                $action = $this->actions->reserve($conversation, $user, $run, $result->actionRequest);
                if ($action->status === ActionRunStatus::Requested) {
                    $action = $this->actions->execute($action);
                    $result = $this->postAction->generate($actor, $agent, $version, $action);
                }
            }
            [$leadCandidate, $resolvedCandidate] = [$result->leadCandidate, $result->resolvedCandidate];

            return DB::transaction(function () use ($authorized, $conversation, $user, $assistant, $result, $leadCandidate, $resolvedCandidate) {
                $c = Conversation::query()->lockForUpdate()->findOrFail($conversation->id);
                $m = ConversationMessage::query()->whereKey($assistant->id)->lockForUpdate()->firstOrFail();
                $fresh = $this->auth->revalidate($authorized);
                $m->complete($fresh, $result->answer, (int) $result->runtimeRunId, $result->confidence, $result->needsHandoff, $result->handoffReason);
                foreach ($result->sources as $i => $source) {
                    $citation = new ConversationMessageCitation;
                    $citation->conversation_message_id = $m->id;
                    $citation->knowledge_chunk_id = (int) $source['knowledge_chunk_id'];
                    $citation->label = $result->citationIds[$i];
                    $citation->rank = $i + 1;
                    $citation->save();
                }$c->finishTurn($fresh, $result->needsHandoff);
                $m = $m->fresh();
                $run = RuntimeRun::query()->findOrFail((int) $result->runtimeRunId);
                $this->outcomes->record($c, $m, $run, $leadCandidate, $resolvedCandidate);
                if ($result->needsHandoff) {
                    $this->handoffs->request($fresh, $c, $m, $run);
                }

                return new ConversationTurnResult($c->fresh(), $user->fresh(), $m->fresh('citations.chunk.source'));
            });
        } catch (\Throwable$e) {
            DB::transaction(function () use ($authorized, $conversation, $assistant) {
                $c = Conversation::query()->lockForUpdate()->findOrFail($conversation->id);
                $m = ConversationMessage::query()->whereKey($assistant->id)->lockForUpdate()->firstOrFail();
                $fresh = $this->auth->revalidate($authorized);
                $m->fail($fresh, 'response_unavailable');
                $c->finishTurn($fresh, false);
            });
            throw $e;
        }
    }

    private function recoverAbandonedTurn(Conversation $conversation, AuthorizedAiLifecycleActor $authorized): void
    {
        if (! $conversation->turn_in_progress) {
            return;
        }
        $pending = $conversation->messages()
            ->where('role', ConversationMessageRole::Assistant->value)
            ->where('status', ConversationMessageStatus::Pending->value)
            ->lockForUpdate()
            ->get();
        if ($pending->count() !== 1) {
            throw new ConversationTurnBusyException('A conversation turn is already in progress.');
        }
        $lease = min(3600, max(60, (int) config('ai.conversation_stale_turn_seconds', 120)));
        $assistant = $pending->first();
        if ($assistant->created_at === null || $assistant->created_at->gt(now()->subSeconds($lease))) {
            throw new ConversationTurnBusyException('A conversation turn is already in progress.');
        }
        $assistant->fail($authorized, 'abandoned_turn');
        $conversation->finishTurn($authorized, false);
    }
}
