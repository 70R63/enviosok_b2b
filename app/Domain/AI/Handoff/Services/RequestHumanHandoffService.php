<?php

namespace App\Domain\AI\Handoff\Services;

use App\Domain\AI\Agents\Data\AuthorizedAiLifecycleActor;
use App\Domain\AI\Conversations\Enums\ConversationMessageRole;
use App\Domain\AI\Conversations\Enums\ConversationMessageStatus;
use App\Domain\AI\Conversations\Models\Conversation;
use App\Domain\AI\Conversations\Models\ConversationMessage;
use App\Domain\AI\Handoff\Enums\HumanHandoffStatus;
use App\Domain\AI\Handoff\Models\HumanHandoff;
use App\Domain\AI\Runtime\Enums\RuntimeRunStatus;
use App\Domain\AI\Runtime\Models\RuntimeRun;

final class RequestHumanHandoffService
{
    public function request(AuthorizedAiLifecycleActor $actor, Conversation $conversation, ConversationMessage $message, RuntimeRun $run): HumanHandoff
    {
        if ((int) $conversation->tenant_id !== $actor->tenantId
            || (int) $message->tenant_id !== (int) $conversation->tenant_id
            || (int) $run->tenant_id !== (int) $conversation->tenant_id
            || (int) $message->conversation_id !== (int) $conversation->id
            || $message->role !== ConversationMessageRole::Assistant
            || $message->status !== ConversationMessageStatus::Completed
            || (int) $message->runtime_run_id !== (int) $run->id
            || $run->status !== RuntimeRunStatus::Completed
            || (int) $run->agent_id !== (int) $conversation->agent_id
            || (int) $run->agent_version_id !== (int) $conversation->agent_version_id
            || ! $message->needs_handoff
            || ! $run->needs_handoff
            || $message->handoff_reason !== $run->handoff_reason) {
            throw new \DomainException('Handoff request evidence is inconsistent.');
        }
        if ($conversation->active_handoff_id) {
            return HumanHandoff::query()->findOrFail($conversation->active_handoff_id);
        }
        $handoff = new HumanHandoff;
        $handoff->conversation_id = $conversation->id;
        $handoff->requested_by_message_id = $message->id;
        $handoff->runtime_run_id = $run->id;
        $handoff->status = HumanHandoffStatus::Requested;
        $handoff->requested_at = now();
        $handoff->reason_code = 'model_requested';
        $handoff->safe_reason = $message->handoff_reason;
        $handoff->save();
        $conversation->requestHuman($actor, $handoff->id);

        return $handoff;
    }
}
