<?php

namespace App\Domain\AI\Conversations\Services;

use App\Domain\AI\Agents\Services\AiLifecycleAuthorization;
use App\Domain\AI\Conversations\Models\Conversation;
use App\Domain\AI\Handoff\Models\HumanHandoff;
use App\Domain\AI\Tenancy\AiTenantBoundary;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CloseConversationService
{
    public function __construct(private AiLifecycleAuthorization $auth, private AiTenantBoundary $tenants) {}

    public function close(User $actor, Conversation $conversation): Conversation
    {
        $authorized = $this->auth->authorize($actor);
        $this->tenants->assertResourceBelongsToCurrentTenant($conversation);

        return DB::transaction(function () use ($authorized, $conversation) {
            $locked = Conversation::query()->lockForUpdate()->findOrFail($conversation->id);
            $fresh = $this->auth->revalidate($authorized);
            if ($locked->active_handoff_id !== null) {
                $handoff = HumanHandoff::query()->whereKey($locked->active_handoff_id)->lockForUpdate()->firstOrFail();
                if ((int) $handoff->conversation_id !== (int) $locked->id) {
                    throw new \DomainException('Handoff aggregate is inconsistent.');
                }
                $handoff->closeBy($fresh);
            }
            $locked->close($fresh);

            return $locked->fresh();
        });
    }
}
