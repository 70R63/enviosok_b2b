<?php

namespace App\Domain\AI\Agents\Services;

use App\Domain\AI\Agents\Data\AuthorizedAiLifecycleActor;
use App\Domain\AI\Core\AiEntitlementGate;
use App\Domain\AI\Core\AiFeatureGate;
use App\Domain\AI\Tenancy\AiTenantBoundary;
use App\Domain\Network\Tenancy\TenantAccessService;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class AiLifecycleAuthorization
{
    public function __construct(private AiFeatureGate $feature, private AiTenantBoundary $tenants, private AiEntitlementGate $entitlements, private TenantAccessService $access) {}

    public function authorize(User $actor): AuthorizedAiLifecycleActor
    {
        $this->feature->ensureEnabled();
        $tenantId = $this->tenants->requireTenantId();
        $this->entitlements->ensureAllowed();
        if (! $this->access->canManageTenant($actor)) throw new AuthorizationException('Only an active tenant owner or admin may manage AI agent lifecycle.');
        return new AuthorizedAiLifecycleActor($tenantId,(int)$actor->getKey(),(string)$this->access->role($actor),now()->toImmutable());
    }

    public function revalidate(AuthorizedAiLifecycleActor $authorization): AuthorizedAiLifecycleActor
    {
        $this->feature->ensureEnabled();
        $tenantId = $this->tenants->requireTenantId();
        if ($tenantId !== $authorization->tenantId) {
            throw new AuthorizationException('Lifecycle authorization does not match the active tenant.');
        }
        $this->entitlements->ensureAllowed();
        $actor = User::query()->find($authorization->actorUserId);
        if (! $actor || ! $this->access->canManageTenant($actor)) {
            throw new AuthorizationException('Lifecycle actor is no longer an active tenant owner or admin.');
        }
        $role = (string) $this->access->role($actor);
        if (! in_array($role, ['owner', 'admin'], true)) {
            throw new AuthorizationException('Lifecycle actor role is no longer authorized.');
        }

        return new AuthorizedAiLifecycleActor($tenantId, (int) $actor->getKey(), $role, now()->toImmutable());
    }
}
