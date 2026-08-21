<?php

namespace App\Domain\Network\ProductShell;

use App\Domain\Network\Billing\{EntitlementService, SubscriptionService};
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Network\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Schema;

final class TenantWorkspaceResolver
{
    public const OPERATIONAL_MODULES = ['B2C', 'QUOTES', 'SHIPPING', 'TRACKING', 'LOCAL_SHIPPING', 'DRIVER'];

    public function __construct(
        private TenantContext $context,
        private SubscriptionService $subscriptions,
        private EntitlementService $entitlements,
    ) {}

    public function resolve(Tenant $tenant): TenantWorkspace
    {
        if ($tenant->status !== 'active' || $this->context->id() !== $tenant->getKey()) {
            throw new AuthorizationException('Tenant workspace unavailable.');
        }

        $subscription = $this->subscriptions->activeForTenant($tenant);
        if (! $subscription) {
            throw new AuthorizationException('Tenant workspace unavailable.');
        }

        $codes = $this->entitlements->forSubscription($subscription)
            ->where('is_enabled', true)->pluck('code')->map(fn ($code) => strtoupper((string) $code));

        if ($codes->intersect(self::OPERATIONAL_MODULES)->isNotEmpty()) {
            return TenantWorkspace::ZigoPlatform;
        }

        if ($codes->contains('AI_CORE')) {
            return TenantWorkspace::ZigoAi;
        }

        throw new AuthorizationException('Tenant workspace unavailable.');
    }

    public function hasAi(Tenant $tenant): bool
    {
        return $this->entitlements->has($tenant, 'AI_CORE');
    }

    public function resolveForPresentation(Tenant $tenant): TenantWorkspace
    {
        if (! Schema::hasTable('network_subscriptions') || ! Schema::hasTable('network_entitlements')) {
            return TenantWorkspace::ZigoPlatform;
        }

        return $this->resolve($tenant);
    }
}
