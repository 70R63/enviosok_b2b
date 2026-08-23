<?php

namespace App\Domain\AI\Handoff\Services;

use App\Domain\AI\Agents\Services\AiLifecycleAuthorization;
use App\Domain\AI\Conversations\Models\Conversation;
use App\Domain\AI\Handoff\Models\HumanHandoff;
use App\Domain\AI\Tenancy\AiTenantBoundary;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class TakeHumanHandoffService
{
    public function __construct(private AiLifecycleAuthorization $auth, private AiTenantBoundary $tenants) {}

    public function take(User $actor, HumanHandoff $handoff): HumanHandoff
    {
        $authorized = $this->auth->authorize($actor);
        $this->tenants->assertResourceBelongsToCurrentTenant($handoff);

        return DB::transaction(function () use ($authorized, $handoff) {
            $c = Conversation::query()->lockForUpdate()->findOrFail($handoff->conversation_id);
            $fresh = $this->auth->revalidate($authorized);
            if ((int) $c->active_handoff_id !== (int) $handoff->id) {
                throw new \DomainException('Handoff is not active for this conversation.');
            }
            $h = HumanHandoff::query()->lockForUpdate()->findOrFail($c->active_handoff_id);
            if ((int) $h->conversation_id !== (int) $c->id) {
                throw new \DomainException('Handoff aggregate is inconsistent.');
            }
            $h->take($fresh);
            $c->activateHuman($fresh);

            return $h->fresh();
        });
    }
}
