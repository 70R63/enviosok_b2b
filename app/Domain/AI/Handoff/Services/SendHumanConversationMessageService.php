<?php

namespace App\Domain\AI\Handoff\Services;

use App\Domain\AI\Agents\Services\AiLifecycleAuthorization;
use App\Domain\AI\Conversations\Enums\ConversationMessageRole;
use App\Domain\AI\Conversations\Enums\ConversationMessageStatus;
use App\Domain\AI\Conversations\Models\Conversation;
use App\Domain\AI\Conversations\Models\ConversationMessage;
use App\Domain\AI\Handoff\Data\HumanConversationMessageData;
use App\Domain\AI\Handoff\Models\HumanHandoff;
use App\Domain\AI\Tenancy\AiTenantBoundary;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SendHumanConversationMessageService
{
    public function __construct(private AiLifecycleAuthorization $auth, private AiTenantBoundary $tenants) {}

    public function send(User $actor, Conversation $conversation, HumanConversationMessageData $data): ConversationMessage
    {
        $authorized = $this->auth->authorize($actor);
        $this->tenants->assertResourceBelongsToCurrentTenant($conversation);

        return DB::transaction(function () use ($authorized, $conversation, $data) {
            $c = Conversation::query()->lockForUpdate()->findOrFail($conversation->id);
            if ($c->active_handoff_id === null) {
                throw new \DomainException('Conversation has no active human handoff.');
            }
            $h = HumanHandoff::query()->whereKey($c->active_handoff_id)->lockForUpdate()->firstOrFail();
            $fresh = $this->auth->revalidate($authorized);
            if ((int) $h->conversation_id !== (int) $c->id) {
                throw new \DomainException('Handoff aggregate is inconsistent.');
            }
            $h->assertAssigned($fresh);
            $m = new ConversationMessage;
            $m->conversation_id = $c->id;
            $m->sequence = $c->reserveHumanMessage($fresh);
            $m->role = ConversationMessageRole::Human;
            $m->status = ConversationMessageStatus::Completed;
            $m->content = $data->message;
            $m->completed_at = now();
            $m->needs_handoff = false;
            $m->save();

            return $m->fresh();
        });
    }
}
