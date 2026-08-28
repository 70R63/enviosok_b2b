<?php

namespace App\Domain\AI\Actions\Services;

use App\Domain\AI\Actions\Enums\ActionRunStatus;
use App\Domain\AI\Actions\Models\ActionRun;
use App\Domain\AI\Agents\Data\AuthorizedAiLifecycleActor;
use App\Domain\AI\Conversations\Enums\ConversationMessageStatus;
use App\Domain\AI\Conversations\Models\Conversation;
use App\Domain\AI\Conversations\Models\ConversationMessage;
use App\Domain\AI\Handoff\Enums\HumanHandoffStatus;
use App\Domain\AI\Handoff\Models\HumanHandoff;
use App\Domain\AI\Runtime\Enums\RuntimeRunStatus;
use App\Domain\AI\Runtime\Models\RuntimeRun;
use Illuminate\Support\Facades\DB;

final class ReconcileActionTurnService
{
    public function reconcileLocked(
        AuthorizedAiLifecycleActor $actor,
        Conversation $conversation,
        ConversationMessage $assistant,
        ActionRun $action,
        RuntimeRun $run,
        string $tokenHash,
    ): HumanHandoff {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('Action reconciliation requires an active transaction.');
        }

        $conversation->assertActiveTurn($tokenHash, $assistant->id);
        if ((int) $conversation->tenant_id !== $actor->tenantId
            || (int) $assistant->tenant_id !== (int) $conversation->tenant_id
            || (int) $assistant->conversation_id !== (int) $conversation->id
            || $assistant->status !== ConversationMessageStatus::Pending
            || ! is_string($assistant->turn_token_hash)
            || ! hash_equals($assistant->turn_token_hash, $tokenHash)
            || (int) $action->tenant_id !== (int) $conversation->tenant_id
            || (int) $action->conversation_id !== (int) $conversation->id
            || (int) $action->agent_id !== (int) $conversation->agent_id
            || (int) $action->agent_version_id !== (int) $conversation->agent_version_id
            || ! is_string($action->conversation_turn_token_hash)
            || ! hash_equals($action->conversation_turn_token_hash, $tokenHash)
            || ! in_array($action->status, [ActionRunStatus::Executing, ActionRunStatus::ReconciliationRequired], true)
            || (int) $run->tenant_id !== (int) $conversation->tenant_id
            || (int) $run->agent_id !== (int) $conversation->agent_id
            || (int) $run->agent_version_id !== (int) $conversation->agent_version_id
            || $run->purpose !== 'post_action_synthesis'
            || ! is_string($run->conversation_turn_token_hash)
            || ! hash_equals($run->conversation_turn_token_hash, $tokenHash)
            || ! in_array($run->status, [RuntimeRunStatus::Started, RuntimeRunStatus::Failed], true)) {
            throw new \DomainException('Action reconciliation evidence is inconsistent.');
        }

        if ($conversation->active_handoff_id) {
            return HumanHandoff::query()->lockForUpdate()->findOrFail($conversation->active_handoff_id);
        }

        if ($action->status === ActionRunStatus::Executing) {
            $action->requireReconciliation($tokenHash);
        }
        if ($run->status === RuntimeRunStatus::Started) {
            $run->fail($actor, 'runtime_failed', null, $tokenHash);
        }
        $assistant->fail($actor, 'action_outcome_unknown', $tokenHash);

        $handoff = new HumanHandoff;
        $handoff->conversation_id = $conversation->id;
        $handoff->requested_by_message_id = $assistant->id;
        $handoff->runtime_run_id = $run->id;
        $handoff->status = HumanHandoffStatus::Requested;
        $handoff->requested_at = now();
        $handoff->reason_code = 'action_outcome_unknown';
        $handoff->safe_reason = 'El resultado de la acción está pendiente de verificación; todavía no existe asignación humana.';
        $handoff->save();

        $conversation->finishTurn($actor, true, $tokenHash, $assistant->id);
        $conversation->requestHuman($actor, $handoff->id);

        return $handoff;
    }
}
