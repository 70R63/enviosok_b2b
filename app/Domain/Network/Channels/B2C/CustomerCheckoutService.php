<?php

namespace App\Domain\Network\Channels\B2C;

use App\Domain\Network\Channels\B2C\Models\{TenantCustomerCheckout, TenantCustomerProfile, TenantOperation};
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Shipping\LastMile\Models\TenantDeliveryProofOption;
use Illuminate\Support\Facades\DB;

final class CustomerCheckoutService
{
    public function __construct(private CustomerCheckoutPricing $pricing) {}

    public function create(Tenant $tenant, TenantCustomerProfile $profile, TenantOperation $operation, ?TenantDeliveryProofOption $proof = null): TenantCustomerCheckout
    {
        abort_unless((int) $profile->tenant_id === (int) $tenant->id && (int) $operation->tenant_id === (int) $tenant->id && (int) $operation->customer_profile_id === (int) $profile->id, 404);
        abort_unless($operation->status === 'quoted' && (! $proof || ($proof->is_active && (int) $proof->tenant_id === (int) $tenant->id)), 422);
        return DB::transaction(function () use ($tenant, $profile, $operation, $proof): TenantCustomerCheckout {
            $operation = TenantOperation::whereKey($operation->id)->lockForUpdate()->firstOrFail();
            $existing = TenantCustomerCheckout::where('tenant_operation_id', $operation->id)->lockForUpdate()->first();
            if ($existing) return $existing;
            $metadata = $operation->metadata ?? [];
            abort_unless(isset($metadata['shipping_data'], $metadata['selected_quote']), 422);
            abort_if(($metadata['selected_quote']['preliminary'] ?? false) === true, 422, 'La cotización preliminar debe finalizarse antes del pago.');
            $amounts = $this->pricing->calculate($operation, $proof);
            $commercial = ['subtotal' => $amounts['subtotal_amount'], 'tax_rate' => $amounts['tax_rate'], 'tax_amount' => $amounts['tax_amount'], 'total' => $amounts['total_amount']];
            return TenantCustomerCheckout::create(array_merge($amounts, [
                'tenant_id' => $tenant->id, 'customer_profile_id' => $profile->id, 'tenant_operation_id' => $operation->id,
                'status' => 'DRAFT', 'payment_status' => 'PENDING', 'expires_at' => now()->addHours(24),
                'quote_snapshot' => array_merge($metadata['selected_quote'], ['route' => ['origin' => data_get($metadata,'shipping_data.sender.address'), 'destination' => data_get($metadata,'shipping_data.recipient.address')], 'package' => data_get($metadata,'shipping_data.package',$metadata['quoted_package'] ?? [])], $commercial),
                'shipping_data_snapshot' => $metadata['shipping_data'],
                'proof_option_snapshot' => $proof ? ['uuid' => $proof->uuid, 'code' => $proof->code, 'name' => $proof->name, 'description' => $proof->description, 'receiver_policy' => $proof->receiver_policy, 'require_receiver_name' => $proof->require_receiver_name, 'require_receiver_type' => $proof->require_receiver_type, 'require_signature' => $proof->require_signature, 'require_photo' => $proof->require_photo, 'require_gps' => $proof->require_gps, 'max_delivery_attempts' => $proof->max_delivery_attempts, 'surcharge_amount' => (float) $proof->surcharge_amount, 'currency' => $proof->currency] : [],
            ]));
        });
    }

    public function pending(TenantCustomerCheckout $checkout): TenantCustomerCheckout
    {
        return DB::transaction(function () use ($checkout): TenantCustomerCheckout {
            $locked = TenantCustomerCheckout::whereKey($checkout->id)->lockForUpdate()->firstOrFail();
            if ($locked->expires_at?->isPast() && $locked->status !== 'PAID') $locked->update(['status' => 'EXPIRED']);
            elseif ($locked->status === 'DRAFT') $locked->update(['status' => 'PENDING_PAYMENT', 'payment_status' => 'PENDING']);
            return $locked->refresh();
        });
    }
}
