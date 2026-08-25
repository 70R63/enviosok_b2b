<?php

namespace App\Domain\AI\Commerce;

use App\Domain\AI\Usage\AiCapacityProvisioningService;
use App\Domain\Network\Billing\Models\{Entitlement, Subscription};
use App\Domain\Network\Catalog\Models\Module;
use App\Domain\Network\Commerce\Models\TenantSaasOrder;
use RuntimeException;

final class AiCommercialActivationService
{
    public function __construct(private AiCapacityProvisioningService $capacities) {}

    public function applies(TenantSaasOrder $order): bool
    {
        return (bool) data_get($order->purchase_snapshot, 'metadata.ai_product', false);
    }

    public function apply(TenantSaasOrder $order, Subscription $subscription): void
    {
        if ((int) $subscription->tenant_id !== (int) $order->tenant_id) {
            throw new RuntimeException('AI_ORDER_TENANT_MISMATCH');
        }
        $metadata = (array) data_get($order->purchase_snapshot, 'metadata', []);
        $module = Module::query()->where('code', 'AI_CORE')->firstOrFail();
        $kind = (string) ($metadata['ai_kind'] ?? 'addon');
        $entitlement = Entitlement::query()->where('subscription_id', $subscription->id)
            ->where('tenant_id', $order->tenant_id)->where('module_id', $module->id)->where('code', 'AI_CORE')
            ->first();

        if (! $entitlement && $kind !== 'base') {
            throw new RuntimeException('AI_CORE_REQUIRED');
        }
        if (! $entitlement) {
            $entitlement = Entitlement::create([
                'subscription_id' => $subscription->id,
                'tenant_id' => $order->tenant_id,
                'module_id' => $module->id,
                'code' => 'AI_CORE',
                'is_enabled' => true,
                'source' => 'override',
            ]);
        } else {
            $entitlement->update(['is_enabled' => true, 'source' => $kind === 'base' ? 'override' : $entitlement->source]);
        }

        if ($kind === 'base') {
            foreach ((array) ($metadata['ai_capacities'] ?? []) as $capability => $quantity) {
                $this->capacities->override($entitlement, (string) $capability, (int) $quantity);
            }
            return;
        }

        if ($kind !== 'addon') {
            throw new RuntimeException('AI_PRODUCT_KIND_UNSUPPORTED');
        }

        $addons = (array) ($metadata['ai_addons'] ?? []);
        if ($addons === []) {
            throw new RuntimeException('AI_ADDON_CAPACITY_MISSING');
        }
        foreach ($addons as $capability => $quantity) {
            $this->capacities->addon($entitlement, (string) $capability, 'order:'.$order->id.':'.$capability, (int) $quantity);
        }
    }
}
