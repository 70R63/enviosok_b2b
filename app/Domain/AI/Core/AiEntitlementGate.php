<?php
namespace App\Domain\AI\Core;
use App\Domain\AI\Support\Exceptions\AiEntitlementException;
use App\Domain\AI\Tenancy\AiTenantBoundary;
use App\Domain\Network\Billing\EntitlementService;
use App\Domain\Network\Billing\SubscriptionService;
final class AiEntitlementGate
{
    public function __construct(
        private AiFeatureGate $featureGate,
        private AiTenantBoundary $tenants,
        private SubscriptionService $subscriptions,
        private EntitlementService $entitlements,
    ) {}
    public function ensureAllowed(): void
    {
        $this->featureGate->ensureEnabled();
        $tenant = $this->tenants->requireTenant();
        $subscription = $this->subscriptions->activeForTenant($tenant);
        if (! $subscription) throw new AiEntitlementException('An active AI subscription is required.');
        $code = strtoupper(trim((string) config('ai.entitlement.module_code', 'AI_CORE')));
        if ($code === '' || ! $this->entitlements->forSubscription($subscription)->contains(fn ($item) => $item->is_enabled && strtoupper($item->code) === $code)) {
            throw new AiEntitlementException('The active subscription does not include AI Core.');
        }
    }
}
