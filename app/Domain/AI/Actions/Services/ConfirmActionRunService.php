<?php

namespace App\Domain\AI\Actions\Services;

use App\Domain\AI\Actions\Enums\ActionRunStatus;
use App\Domain\AI\Actions\Exceptions\ActionConflictException;
use App\Domain\AI\Actions\Models\ActionRun;
use App\Domain\AI\Agents\Services\AiLifecycleAuthorization;
use App\Domain\AI\Conversations\Enums\ConversationChannel;
use App\Domain\AI\Conversations\Enums\ConversationMessageRole;
use App\Domain\AI\Conversations\Enums\ConversationMessageStatus;
use App\Domain\AI\Conversations\Models\Conversation;
use App\Domain\AI\Conversations\Models\ConversationMessage;
use App\Domain\AI\Handoff\Services\RequestHumanHandoffService;
use App\Domain\AI\Leads\Services\RecordLeadOutcomeCandidatesService;
use App\Domain\AI\Runtime\Enums\AiExecutionMode;
use App\Domain\AI\Runtime\Models\RuntimeRun;
use App\Domain\AI\Tenancy\AiTenantBoundary;
use App\Domain\AI\Usage\AiCapacityService;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ConfirmActionRunService
{
    public function __construct(
        private AiLifecycleAuthorization $auth,
        private AiTenantBoundary $tenants,
        private ActionExecutor $actions,
        private GeneratePostActionResponseService $postAction,
        private RecordLeadOutcomeCandidatesService $outcomes,
        private RequestHumanHandoffService $handoffs,
        private AiCapacityService $capacity,
    ) {}

    public function confirm(User $actor, ActionRun $action, ?callable $beforeClaim = null): ConversationMessage
    {
        $authorized = $this->auth->authorize($actor);
        $this->tenants->assertResourceBelongsToCurrentTenant($action);
        [$conversation, $message, $agent, $version, $claimed, $postActionRun] = DB::transaction(function () use ($action, $authorized, $actor, $beforeClaim): array {
            $tenant = Tenant::query()->findOrFail($authorized->tenantId);
            $this->capacity->lockAuthority($tenant);
            if ($beforeClaim) {
                $beforeClaim($action);
            }
            $conversation = Conversation::query()->lockForUpdate()->findOrFail($action->conversation_id);
            $lockedAction = ActionRun::query()->where('conversation_id', $conversation->id)->whereKey($action->id)->lockForUpdate()->firstOrFail();
            if ($lockedAction->status !== ActionRunStatus::AwaitingConfirmation) {
                throw new ActionConflictException('Action is no longer awaiting confirmation.');
            }
            $mode = in_array($conversation->channel, [ConversationChannel::Webchat, ConversationChannel::WhatsApp], true) ? AiExecutionMode::Live : AiExecutionMode::Simulation;
            $agent = $conversation->agent()->firstOrFail();
            $version = $conversation->agentVersion()->firstOrFail();
            $postActionRun = $this->postAction->reserve($actor, $agent, $version, $lockedAction, $mode);
            $fresh = $this->auth->revalidate($authorized);
            $sequence = $conversation->reserveActionCompletion($fresh);
            $message = new ConversationMessage;
            $message->conversation_id = $conversation->id;
            $message->sequence = $sequence;
            $message->role = ConversationMessageRole::Assistant;
            $message->status = ConversationMessageStatus::Pending;
            $message->content = null;
            $message->needs_handoff = false;
            $message->save();
            $claimed = $this->actions->claimConfirmation($lockedAction, $actor->id);

            return [$conversation, $message, $agent, $version, $claimed, $postActionRun];
        });

        try {
            $executed = $this->actions->performClaimed($claimed);
            if ($executed->status !== ActionRunStatus::Succeeded) {
                $this->postAction->failReserved($actor, $postActionRun);
                throw new \DomainException('Action execution failed.');
            }
            $result = $this->postAction->generateReserved($actor, $agent, $version, $executed, $postActionRun);

            return DB::transaction(function () use ($conversation, $message, $result, $authorized): ConversationMessage {
                $lockedConversation = Conversation::query()->lockForUpdate()->findOrFail($conversation->id);
                $lockedMessage = ConversationMessage::query()->lockForUpdate()->findOrFail($message->id);
                $fresh = $this->auth->revalidate($authorized);
                $lockedMessage->complete($fresh, $result->answer, (int) $result->runtimeRunId, $result->confidence, $result->needsHandoff, $result->handoffReason);
                $lockedConversation->finishTurn($fresh, $result->needsHandoff);
                $run = RuntimeRun::query()->findOrFail((int) $result->runtimeRunId);
                $this->outcomes->record($lockedConversation, $lockedMessage, $run, $result->leadCandidate, $result->resolvedCandidate);
                if ($result->needsHandoff) {
                    $this->handoffs->request($fresh, $lockedConversation, $lockedMessage, $run);
                }

                return $lockedMessage->fresh();
            });
        } catch (\Throwable $e) {
            $this->postAction->failReserved($actor, $postActionRun);
            DB::transaction(function () use ($conversation, $message, $authorized): void {
                $lockedConversation = Conversation::query()->lockForUpdate()->findOrFail($conversation->id);
                $lockedMessage = ConversationMessage::query()->lockForUpdate()->findOrFail($message->id);
                $fresh = $this->auth->revalidate($authorized);
                $lockedMessage->fail($fresh, 'action_completion_failed');
                $lockedConversation->finishTurn($fresh, false);
            });
            throw $e;
        }
    }
}
