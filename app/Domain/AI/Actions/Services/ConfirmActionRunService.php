<?php

namespace App\Domain\AI\Actions\Services;

use App\Domain\AI\Actions\Enums\ActionRunStatus;
use App\Domain\AI\Actions\Exceptions\ActionConflictException;
use App\Domain\AI\Actions\Models\ActionRun;
use App\Domain\AI\Agents\Models\{Agent, AgentVersion};
use App\Domain\AI\Agents\Services\AiLifecycleAuthorization;
use App\Domain\AI\Conversations\Enums\ConversationChannel;
use App\Domain\AI\Conversations\Enums\ConversationMessageRole;
use App\Domain\AI\Conversations\Enums\ConversationMessageStatus;
use App\Domain\AI\Conversations\Models\Conversation;
use App\Domain\AI\Conversations\Models\ConversationMessage;
use App\Domain\AI\Conversations\Exceptions\ConversationTurnSupersededException;
use App\Domain\AI\Conversations\Support\ConversationTurnLease;
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
        private ConversationTurnLease $leases,
        private ReconcileActionTurnService $actionReconciliation,
    ) {}

    public function confirm(User $actor, ActionRun $action, ?callable $beforeClaim = null): ConversationMessage
    {
        $authorized = $this->auth->authorize($actor);
        $this->tenants->assertResourceBelongsToCurrentTenant($action);
        $tokenHash=$this->leases->newTokenHash();[$startedAt,$expiresAt]=$this->leases->window();
        [$conversation, $message, $agent, $version, $claimed, $postActionRun, $mode] = DB::transaction(function () use ($action, $authorized, $actor, $beforeClaim,$tokenHash,$startedAt,$expiresAt): array {
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
            $fresh = $this->auth->revalidate($authorized);
            $sequence = $conversation->reserveActionCompletion($fresh,$tokenHash,$startedAt,$expiresAt);
            $message = new ConversationMessage;
            $message->conversation_id = $conversation->id;
            $message->turn_token_hash = $tokenHash;
            $message->sequence = $sequence;
            $message->role = ConversationMessageRole::Assistant;
            $message->status = ConversationMessageStatus::Pending;
            $message->content = null;
            $message->needs_handoff = false;
            $message->save();
            $conversation->bindActiveTurnAssistant($fresh,$message,$tokenHash);
            $postActionRun = $this->postAction->reserve($actor, $agent, $version, $lockedAction, $mode,$tokenHash);
            $claimed = $this->actions->claimConfirmation($lockedAction, $actor->id,$tokenHash,$expiresAt);

            return [$conversation, $message, $agent, $version, $claimed, $postActionRun,$mode];
        });

        try {
            $this->heartbeat($authorized,$conversation->id,$message->id,$tokenHash);
            $executed = $this->actions->performClaimed($claimed,$tokenHash);
            if($executed->status===ActionRunStatus::ReconciliationRequired){$this->reconcile($authorized,$conversation->id,$message->id,$postActionRun->id,$tokenHash);throw new \DomainException('Action outcome requires reconciliation.');}
            if ($executed->status !== ActionRunStatus::Succeeded) {
                $this->postAction->failReserved($actor, $postActionRun, $tokenHash);
                throw new \DomainException('Action execution failed.');
            }
            $this->heartbeat($authorized,$conversation->id,$message->id,$tokenHash);
            $result = $this->postAction->generateReserved($actor, $agent, $version, $executed, $postActionRun, $tokenHash);
            $this->heartbeat($authorized,$conversation->id,$message->id,$tokenHash);

            return DB::transaction(function () use ($conversation, $message, $result, $authorized,$tokenHash,$mode,$executed): ConversationMessage {
                $lockedConversation = Conversation::query()->lockForUpdate()->findOrFail($conversation->id);
                $lockedConversation->assertActiveTurn($tokenHash,$message->id);
                $lockedMessage = ConversationMessage::query()->lockForUpdate()->findOrFail($message->id);
                $lockedAction=ActionRun::query()->lockForUpdate()->findOrFail($executed->id);
                $run = RuntimeRun::query()->lockForUpdate()->findOrFail((int) $result->runtimeRunId);
                $lockedAgent = Agent::query()->lockForUpdate()->findOrFail($lockedConversation->agent_id);
                $lockedVersion = AgentVersion::query()->lockForUpdate()->findOrFail($lockedConversation->agent_version_id);
                if((int)$lockedAgent->tenant_id!==(int)$lockedConversation->tenant_id||(int)$lockedVersion->tenant_id!==(int)$lockedConversation->tenant_id||(int)$lockedVersion->agent_id!==(int)$lockedAgent->id||$lockedMessage->status!==ConversationMessageStatus::Pending||!hash_equals((string)$lockedMessage->turn_token_hash,$tokenHash)||(int)$lockedAction->tenant_id!==(int)$lockedConversation->tenant_id||(int)$lockedAction->agent_id!==(int)$lockedAgent->id||(int)$lockedAction->agent_version_id!==(int)$lockedVersion->id||$lockedAction->status!==ActionRunStatus::Succeeded||!hash_equals((string)$lockedAction->conversation_turn_token_hash,$tokenHash)||(int)$run->tenant_id!==(int)$lockedConversation->tenant_id||(int)$run->agent_id!==(int)$lockedAgent->id||(int)$run->agent_version_id!==(int)$lockedVersion->id||!hash_equals((string)$run->conversation_turn_token_hash,$tokenHash)||$run->purpose!=='post_action_synthesis'||$run->execution_mode!==$mode||$run->status!==\App\Domain\AI\Runtime\Enums\RuntimeRunStatus::Completed)throw new \DomainException('Action completion aggregate is incompatible.');
                $fresh = $this->auth->revalidate($authorized);
                $lockedMessage->complete($fresh, $result->answer, $run->id, $result->confidence, $result->needsHandoff, $result->handoffReason,$tokenHash,'post_action_synthesis',$mode->value);
                $lockedConversation->finishTurn($fresh, $result->needsHandoff,$tokenHash,$lockedMessage->id);
                $this->outcomes->record($lockedConversation, $lockedMessage, $run, $result->leadCandidate, $result->resolvedCandidate);
                if ($result->needsHandoff) {
                    $this->handoffs->request($fresh, $lockedConversation, $lockedMessage, $run);
                }

                return $lockedMessage->fresh();
            });
        } catch (\Throwable $e) {
            try {$this->postAction->failReserved($actor, $postActionRun, $tokenHash);} catch (ConversationTurnSupersededException) {}
            try{DB::transaction(function () use ($conversation, $message, $authorized,$tokenHash): void {$lockedConversation=Conversation::query()->lockForUpdate()->findOrFail($conversation->id);$lockedConversation->assertActiveTurn($tokenHash,$message->id);$lockedMessage=ConversationMessage::query()->lockForUpdate()->findOrFail($message->id);$fresh=$this->auth->revalidate($authorized);if($lockedMessage->status===ConversationMessageStatus::Pending)$lockedMessage->fail($fresh,'action_completion_failed',$tokenHash);$lockedConversation->finishTurn($fresh,false,$tokenHash,$message->id);});}catch(ConversationTurnSupersededException){}
            throw $e;
        }
    }

    private function heartbeat($authorized,int$conversationId,int$messageId,string$tokenHash):void{DB::transaction(function()use($authorized,$conversationId,$messageId,$tokenHash){$c=Conversation::query()->lockForUpdate()->findOrFail($conversationId);$c->assertActiveTurn($tokenHash,$messageId);$c->heartbeatTurn($this->auth->revalidate($authorized),$tokenHash,$this->leases->renewedExpiry());});}
    private function reconcile($authorized,int$conversationId,int$messageId,int$runtimeRunId,string$tokenHash):void
    {
        DB::transaction(function()use($authorized,$conversationId,$messageId,$runtimeRunId,$tokenHash):void{
            $c=Conversation::query()->lockForUpdate()->findOrFail($conversationId);$c->assertActiveTurn($tokenHash,$messageId);
            $m=ConversationMessage::query()->lockForUpdate()->findOrFail($messageId);
            $run=RuntimeRun::query()->lockForUpdate()->findOrFail($runtimeRunId);
            $fresh=$this->auth->revalidate($authorized);
            $action=ActionRun::query()->where('conversation_id',$c->id)->where('conversation_turn_token_hash',$tokenHash)->lockForUpdate()->firstOrFail();
            $this->actionReconciliation->reconcileLocked($fresh,$c,$m,$action,$run,$tokenHash);
        });
    }
}
