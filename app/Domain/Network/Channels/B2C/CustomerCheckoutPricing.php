<?php

namespace App\Domain\Network\Channels\B2C;

use App\Domain\Network\Channels\B2C\Models\TenantOperation;
use App\Domain\Shipping\LastMile\Models\TenantDeliveryProofOption;

final class CustomerCheckoutPricing
{
    public function calculate(TenantOperation $operation, ?TenantDeliveryProofOption $proof = null): array
    {
        $selected = $operation->metadata['selected_quote'] ?? [];
        $shipping = round((float) ($selected['price'] ?? $operation->metadata['final_price'] ?? 0), 2);
        abort_unless($shipping >= 0, 422);
        $evidence = round((float) ($proof?->surcharge_amount ?? 0), 2);
        $subtotal = round($shipping + $evidence, 2);
        $taxRate = 0.16;
        $tax = round($subtotal * $taxRate, 2);
        return ['currency' => (string) ($selected['currency'] ?? $proof?->currency ?? 'MXN'), 'shipping_amount' => $shipping, 'evidence_amount' => $evidence, 'subtotal_amount' => $subtotal, 'tax_rate' => $taxRate, 'tax_amount' => $tax, 'total_amount' => round($subtotal + $tax, 2)];
    }
}
