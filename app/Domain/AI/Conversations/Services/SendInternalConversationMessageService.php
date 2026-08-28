<?php

namespace App\Domain\AI\Conversations\Services;

use App\Domain\AI\Actions\Enums\ActionRunStatus;
use App\Domain\AI\Actions\Models\ActionRun;
use App\Domain\AI\Actions\Services\{ActionExecutor, GeneratePostActionResponseService, ReconcileActionTurnService};
use App\Domain\AI\Agents\Data\AuthorizedAiLifecycleActor;
use App\Domain\AI\Agents\Enums\AgentVersionStatus;
use App\Domain\AI\Agents\Models\{Agent, AgentVersion};
use App\Domain\AI\Agents\Services\AiLifecycleAuthorization;
use App\Domain\AI\Conversations\Data\{ConversationTurnResult, SendConversationMessageData};
use App\Domain\AI\Conversations\Enums\{ConversationChannel, ConversationMessageRole, ConversationMessageStatus};
use App\Domain\AI\Conversations\Exceptions\{ConversationTurnBusyException, ConversationTurnSupersededException};
use App\Domain\AI\Conversations\Models\{Conversation, ConversationMessage, ConversationMessageCitation};
use App\Domain\AI\Conversations\Support\ConversationTurnLease;
use App\Domain\AI\Handoff\Services\RequestHumanHandoffService;
use App\Domain\AI\Leads\Services\RecordLeadOutcomeCandidatesService;
use App\Domain\AI\Runtime\Enums\{AiExecutionMode, RuntimeRunStatus};
use App\Domain\AI\Runtime\Models\RuntimeRun;
use App\Domain\AI\Runtime\Services\GenerateAgentDraftResponseService;
use App\Domain\AI\Tenancy\AiTenantBoundary;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class SendInternalConversationMessageService
{
    public function __construct(
        private AiLifecycleAuthorization $auth,
        private AiTenantBoundary $tenants,
        private GenerateAgentDraftResponseService $runtime,
        private RecordLeadOutcomeCandidatesService $outcomes,
        private RequestHumanHandoffService $handoffs,
        private ActionExecutor $actions,
        private GeneratePostActionResponseService $postAction,
        private ConversationTurnLease $leases,
        private ReconcileActionTurnService $actionReconciliation,
    ) {}

    public function send(User $actor, Conversation $conversation, SendConversationMessageData $data, ?callable $beforeReserve = null): ConversationTurnResult
    {
        $this->leases->settings();
        $authorized = $this->auth->authorize($actor);
        $this->tenants->assertResourceBelongsToCurrentTenant($conversation);
        $tokenHash = $this->leases->newTokenHash();
        $reservation = DB::transaction(function () use ($authorized, $conversation, $data, $beforeReserve, $tokenHash): array {
            if ($beforeReserve) $beforeReserve();
            $c = Conversation::query()->lockForUpdate()->findOrFail($conversation->id);
            $fresh = $this->auth->revalidate($authorized);
            if ($this->recoverAbandonedTurn($c, $fresh)) return ['recovered'];
            if(Schema::hasTable('ai_action_runs')&&ActionRun::query()->where('conversation_id',$c->id)->where('status',ActionRunStatus::AwaitingConfirmation->value)->lockForUpdate()->exists())throw new ConversationTurnBusyException('A conversation Action is awaiting confirmation.');
            $agent = Agent::query()->whereKey($c->agent_id)->lockForUpdate()->firstOrFail();
            $version = AgentVersion::query()->where('agent_id', $agent->id)->whereKey($c->agent_version_id)->lockForUpdate()->firstOrFail();
            $allowed = in_array($c->channel, [ConversationChannel::Webchat, ConversationChannel::WhatsApp], true) ? [AgentVersionStatus::Published, AgentVersionStatus::Retired] : [AgentVersionStatus::Draft, AgentVersionStatus::Testing];
            if (! in_array($version->status, $allowed, true)) throw new \DomainException('The pinned Agent Version is no longer executable.');
            $history = $c->messages()->where('status', ConversationMessageStatus::Completed->value)->orderByDesc('sequence')->limit(6)->get()->reverse()->map(fn ($m) => ['role' => $m->role->value, 'content' => $m->content])->values()->all();
            [$startedAt, $expiresAt] = $this->leases->window();
            [$userSequence, $assistantSequence] = $c->reserveTurn($fresh, $tokenHash, $startedAt, $expiresAt);
            $user = new ConversationMessage;
            $user->conversation_id = $c->id; $user->turn_token_hash = $tokenHash; $user->sequence = $userSequence; $user->role = ConversationMessageRole::User; $user->status = ConversationMessageStatus::Completed; $user->content = $data->message->value; $user->completed_at = now(); $user->save();
            $assistant = new ConversationMessage;
            $assistant->conversation_id = $c->id; $assistant->turn_token_hash = $tokenHash; $assistant->sequence = $assistantSequence; $assistant->role = ConversationMessageRole::Assistant; $assistant->status = ConversationMessageStatus::Pending; $assistant->content = null; $assistant->needs_handoff = false; $assistant->save();
            $c->bindActiveTurnAssistant($fresh, $assistant, $tokenHash);
            return ['reserved', $user, $assistant, $agent, $version, $history];
        });
        if ($reservation[0] === 'recovered') throw new ConversationTurnBusyException('The previous conversation turn was safely recovered. Please retry.');
        [, $user, $assistant, $agent, $version, $history] = $reservation;
        $mode = in_array($conversation->channel, [ConversationChannel::Webchat, ConversationChannel::WhatsApp], true) ? AiExecutionMode::Live : AiExecutionMode::Simulation;

        try {
            $this->heartbeat($authorized, $conversation->id, $assistant->id, $tokenHash);
            $result = $this->runtime->generate($actor, $agent, $data->message, $version, $history, $mode, $tokenHash);
            $this->heartbeat($authorized, $conversation->id, $assistant->id, $tokenHash);
            if ($result->actionRequest) {
                $run = $this->lockedCompatibleRun($conversation->id, $assistant->id, $tokenHash, (int) $result->runtimeRunId, $mode);
                $user = DB::transaction(function () use ($authorized, $conversation, $user, $run, $tokenHash) {
                    $c = Conversation::query()->lockForUpdate()->findOrFail($conversation->id); $c->assertActiveTurn($tokenHash);
                    $message = ConversationMessage::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                    $message->linkSourceRuntimeRun($this->auth->revalidate($authorized), $run->id); return $message->fresh();
                });
                $action = $this->actions->reserve($conversation, $user, $run, $result->actionRequest);
                if ($action->status === ActionRunStatus::Requested) {
                    $this->heartbeat($authorized, $conversation->id, $assistant->id, $tokenHash);
                    $postActionRun = null;
                    $action = $this->actions->execute($action, $tokenHash, $conversation->fresh()->active_turn_expires_at, function (ActionRun $locked) use (&$postActionRun, $actor, $agent, $version, $mode, $tokenHash): void { $postActionRun = $this->postAction->reserve($actor, $agent, $version, $locked, $mode, $tokenHash); });
                    $this->heartbeat($authorized, $conversation->id, $assistant->id, $tokenHash);
                    if (! $postActionRun instanceof RuntimeRun) throw new \LogicException('Post-action Runtime was not reserved.');
                    if ($action->status === ActionRunStatus::ReconciliationRequired) { $this->reconcileOwnedTurn($authorized,$conversation->id,$assistant->id,$tokenHash); throw new \DomainException('Action outcome requires reconciliation.'); }
                    if ($action->status !== ActionRunStatus::Succeeded) { $this->postAction->failReserved($actor, $postActionRun, $tokenHash); throw new \DomainException('Action execution failed.'); }
                    $result = $this->postAction->generateReserved($actor, $agent, $version, $action, $postActionRun, $tokenHash);
                    $this->heartbeat($authorized, $conversation->id, $assistant->id, $tokenHash);
                }
            }
            [$leadCandidate, $resolvedCandidate] = [$result->leadCandidate, $result->resolvedCandidate];
            return DB::transaction(function () use ($authorized, $conversation, $user, $assistant, $result, $leadCandidate, $resolvedCandidate, $tokenHash, $mode) {
                $c = Conversation::query()->lockForUpdate()->findOrFail($conversation->id); $c->assertActiveTurn($tokenHash, $assistant->id);
                $m = ConversationMessage::query()->whereKey($assistant->id)->lockForUpdate()->firstOrFail();
                $run = RuntimeRun::query()->whereKey((int) $result->runtimeRunId)->lockForUpdate()->firstOrFail();
                $this->assertRun($c, $m, $run, $tokenHash, $mode);
                $fresh = $this->auth->revalidate($authorized);
                $m->complete($fresh, $result->answer, $run->id, $result->confidence, $result->needsHandoff, $result->handoffReason, $tokenHash, $run->purpose, $mode->value);
                foreach ($result->sources as $i => $source) { $citation = new ConversationMessageCitation; $citation->conversation_message_id = $m->id; $citation->knowledge_chunk_id = (int) $source['knowledge_chunk_id']; $citation->label = $result->citationIds[$i]; $citation->rank = $i + 1; $citation->save(); }
                $c->finishTurn($fresh, $result->needsHandoff, $tokenHash, $m->id);
                $m = $m->fresh(); $this->outcomes->record($c, $m, $run, $leadCandidate, $resolvedCandidate);
                if ($result->needsHandoff) $this->handoffs->request($fresh, $c, $m, $run);
                return new ConversationTurnResult($c->fresh(), $user->fresh(), $m->fresh('citations.chunk.source'));
            });
        } catch (\Throwable $e) {
            $this->failOwnedTurn($authorized, $conversation->id, $assistant->id, $tokenHash);
            throw $e;
        }
    }

    private function heartbeat(AuthorizedAiLifecycleActor $authorized, int $conversationId, int $assistantId, string $tokenHash): void
    {
        DB::transaction(function () use ($authorized, $conversationId, $assistantId, $tokenHash): void {
            $c = Conversation::query()->lockForUpdate()->findOrFail($conversationId); $c->assertActiveTurn($tokenHash, $assistantId);
            $m = ConversationMessage::query()->whereKey($assistantId)->lockForUpdate()->firstOrFail();
            if ($m->status !== ConversationMessageStatus::Pending || ! hash_equals((string) $m->turn_token_hash, $tokenHash)) throw new ConversationTurnSupersededException;
            $c->heartbeatTurn($this->auth->revalidate($authorized), $tokenHash, $this->leases->renewedExpiry());
        });
    }

    private function recoverAbandonedTurn(Conversation $conversation, AuthorizedAiLifecycleActor $authorized): bool
    {
        if (! $conversation->turn_in_progress) return false;
        if (! is_string($conversation->active_turn_token_hash) || ! $conversation->active_turn_assistant_message_id || ! $conversation->active_turn_expires_at || ! $conversation->active_turn_heartbeat_at) throw new ConversationTurnBusyException('A conversation turn is already in progress.');
        [, , $lease] = $this->leases->settings();
        if ($conversation->active_turn_expires_at->isFuture() || $conversation->active_turn_heartbeat_at->gt(now()->subSeconds($lease))) throw new ConversationTurnBusyException('A conversation turn is already in progress.');
        $tokenHash = (string) $conversation->active_turn_token_hash;
        $assistant = ConversationMessage::query()->whereKey($conversation->active_turn_assistant_message_id)->lockForUpdate()->firstOrFail();
        $conversation->assertActiveTurn($tokenHash, $assistant->id);
        if ($assistant->status !== ConversationMessageStatus::Pending || ! hash_equals((string) $assistant->turn_token_hash, $tokenHash)) throw new ConversationTurnBusyException('A conversation turn is already in progress.');
        $action=Schema::hasTable('ai_action_runs')?ActionRun::query()->where('conversation_id',$conversation->id)->where('conversation_turn_token_hash',$tokenHash)->whereIn('status',[ActionRunStatus::Executing->value,ActionRunStatus::ReconciliationRequired->value])->lockForUpdate()->first():null;
        if($action){$run=RuntimeRun::query()->where('tenant_id',$conversation->tenant_id)->where('agent_id',$conversation->agent_id)->where('agent_version_id',$conversation->agent_version_id)->where('conversation_turn_token_hash',$tokenHash)->where('purpose','post_action_synthesis')->lockForUpdate()->firstOrFail();$this->actionReconciliation->reconcileLocked($authorized,$conversation,$assistant,$action,$run,$tokenHash);return true;}
        $assistant->fail($authorized, 'abandoned_turn', $tokenHash); $conversation->finishTurn($authorized, false, $tokenHash, $assistant->id);
        return true;
    }

    private function failOwnedTurn(AuthorizedAiLifecycleActor $authorized, int $conversationId, int $assistantId, string $tokenHash): void
    {
        try {
            DB::transaction(function () use ($authorized, $conversationId, $assistantId, $tokenHash): void {
                $c = Conversation::query()->lockForUpdate()->findOrFail($conversationId); $c->assertActiveTurn($tokenHash, $assistantId);
                $m = ConversationMessage::query()->whereKey($assistantId)->lockForUpdate()->firstOrFail();
                if ($m->status !== ConversationMessageStatus::Pending) return;
                $fresh = $this->auth->revalidate($authorized); $m->fail($fresh, 'response_unavailable', $tokenHash); $c->finishTurn($fresh, false, $tokenHash, $m->id);
            });
        } catch (ConversationTurnSupersededException) {
            // A fenced callback is intentionally a no-op.
        }
    }

    private function reconcileOwnedTurn(AuthorizedAiLifecycleActor $authorized,int $conversationId,int $assistantId,string $tokenHash):void
    {
        DB::transaction(function()use($authorized,$conversationId,$assistantId,$tokenHash):void{$c=Conversation::query()->lockForUpdate()->findOrFail($conversationId);$c->assertActiveTurn($tokenHash,$assistantId);$m=ConversationMessage::query()->whereKey($assistantId)->lockForUpdate()->firstOrFail();if($m->status===ConversationMessageStatus::Pending)$m->fail($this->auth->revalidate($authorized),'action_outcome_unknown',$tokenHash);$c->finishTurn($this->auth->revalidate($authorized),true,$tokenHash,$assistantId);});
    }

    private function lockedCompatibleRun(int $conversationId, int $assistantId, string $tokenHash, int $runId, AiExecutionMode $mode): RuntimeRun
    {
        return DB::transaction(function () use ($conversationId, $assistantId, $tokenHash, $runId, $mode): RuntimeRun {
            $c = Conversation::query()->lockForUpdate()->findOrFail($conversationId); $c->assertActiveTurn($tokenHash, $assistantId);
            $m = ConversationMessage::query()->whereKey($assistantId)->lockForUpdate()->firstOrFail(); $run = RuntimeRun::query()->whereKey($runId)->lockForUpdate()->firstOrFail();
            $this->assertRun($c, $m, $run, $tokenHash, $mode); return $run;
        });
    }

    private function assertRun(Conversation $conversation, ConversationMessage $message, RuntimeRun $run, string $tokenHash, AiExecutionMode $mode): void
    {
        $purposes = $mode === AiExecutionMode::Live ? ['public_webchat', 'post_action_synthesis'] : ['agent_draft_simulation', 'post_action_synthesis'];
        if ((int) $message->conversation_id !== (int) $conversation->id || (int) $run->tenant_id !== (int) $conversation->tenant_id || (int) $run->agent_id !== (int) $conversation->agent_id || (int) $run->agent_version_id !== (int) $conversation->agent_version_id || ! is_string($run->conversation_turn_token_hash) || ! hash_equals($run->conversation_turn_token_hash, $tokenHash) || ! in_array($run->status, [RuntimeRunStatus::Completed, RuntimeRunStatus::SkippedNoKnowledge], true) || ! in_array($run->purpose, $purposes, true) || $run->execution_mode !== $mode) throw new \DomainException('Runtime Run is incompatible with the conversation turn.');
    }
}
