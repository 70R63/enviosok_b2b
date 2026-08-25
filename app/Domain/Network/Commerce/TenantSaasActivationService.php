<?php

namespace App\Domain\Network\Commerce;

use App\Domain\ApiHub\Models\TenantApiQuotaPolicy;
use App\Domain\Network\Billing\Models\Entitlement;
use App\Domain\Network\Billing\SaasSubscriptionLifecycleService;
use App\Domain\Network\Billing\SubscriptionService;
use App\Domain\Network\Commerce\Models\TenantOperationAllowance;
use App\Domain\Network\Commerce\Models\TenantSaasOrder;
use App\Domain\AI\Commerce\AiCommercialActivationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class TenantSaasActivationService
{
    public function __construct(
        private SubscriptionService $subscriptions,
        private SaasSubscriptionLifecycleService $lifecycle,
        private AiCommercialActivationService $aiProducts,
    ) {}

    public function activate(TenantSaasOrder $order): TenantSaasOrder
    {
        $activated = DB::transaction(function () use ($order): TenantSaasOrder {
            $locked = TenantSaasOrder::with(['tenant', 'product'])->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'ACTIVATED') {
                return $locked;
            }
            if ($locked->status !== 'PAID' || $locked->payment_status !== 'APPROVED' || $locked->expires_at?->isPast()) {
                throw new RuntimeException('ORDER_NOT_ACTIVATABLE');
            }

            $snapshot = $locked->purchase_snapshot;
            $type = $snapshot['type'];
            if ($this->aiProducts->applies($locked)) {
                $subscription = $this->subscriptions->currentForTenant($locked->tenant) ?? throw new RuntimeException('SUBSCRIPTION_REQUIRED');
                $this->aiProducts->apply($locked, $subscription);
            } elseif ($type === 'PLAN') {
                $this->subscriptions->activatePaidPlan($locked->tenant, (int) $snapshot['plan_id'], $snapshot['billing_type'], $locked->created_by_user_id);
            } elseif ($type === 'MODULE' || $type === 'ADDON') {
                $subscription = $this->subscriptions->currentForTenant($locked->tenant) ?? throw new RuntimeException('SUBSCRIPTION_REQUIRED');
                $module = \App\Domain\Network\Catalog\Models\Module::findOrFail((int) ($snapshot['module_id'] ?? 0));
                Entitlement::updateOrCreate(['subscription_id' => $subscription->id, 'module_id' => $module->id], ['tenant_id' => $locked->tenant_id, 'code' => $module->code, 'is_enabled' => true, 'source' => 'override']);
                if ($module->code === 'API' && Schema::hasTable('tenant_api_quota_policies')) {
                    $metadata = $snapshot['metadata'] ?? [];
                    $monthly = (int) ($metadata['api_monthly_request_limit'] ?? 0);
                    $rate = (int) ($metadata['api_rate_limit_per_minute'] ?? 0);
                    if ($monthly > 0 && $rate > 0) {
                        TenantApiQuotaPolicy::firstOrCreate(['tenant_id' => $locked->tenant_id, 'source_saas_order_id' => $locked->id], ['monthly_request_limit' => $monthly, 'rate_limit_per_minute' => $rate, 'valid_from' => now(), 'valid_until' => $subscription->current_period_end]);
                    }
                }
            } elseif ($type === 'OPERATION_PACK') {
                $subscription = $this->subscriptions->currentForTenant($locked->tenant) ?? throw new RuntimeException('SUBSCRIPTION_REQUIRED');
                $operations = (int) ($snapshot['included_operations'] ?? 0);
                if ($operations < 1) {
                    throw new RuntimeException('ALLOWANCE_REQUIRED');
                }
                TenantOperationAllowance::firstOrCreate(['saas_order_id' => $locked->id], ['tenant_id' => $locked->tenant_id, 'subscription_id' => $subscription->id, 'operations' => $operations, 'starts_at' => now(), 'expires_at' => $subscription->current_period_end]);
            } else {
                throw new RuntimeException('ACTIVATION_NOT_IMPLEMENTED');
            }

            $locked->update(['status' => 'ACTIVATED', 'activated_at' => now()]);
            return $locked->fresh();
        });

        // The provider approval remains authoritative. This only restores access after
        // an already-paid PLAN order was activated successfully.
        if (($activated->purchase_snapshot['type'] ?? null) === 'PLAN' && str_starts_with($activated->purchase_key, 'renewal:')) {
            $this->lifecycle->reactivateAfterVerifiedRenewal($activated);
        }

        return $activated;
    }
}
