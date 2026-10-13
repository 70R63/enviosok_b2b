<?php

namespace App\Domain\Network\Providers;

use App\Domain\Network\Tenancy\Models\Tenant;
use App\Models\ZigoPricingRule;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class LocalProviderCommercialPricing
{
    /** Explicit commercial namespace for this consumer/provider pair. */
    public function carrierKey(Tenant $consumer, Tenant $provider): string
    {
        return 'LOCAL_'.strtoupper(substr(hash('sha256', $consumer->uuid.'|'.$provider->uuid), 0, 40));
    }

    public function calculate(Tenant $consumer, Tenant $provider, string $cost, string $segment, string $package): ?array
    {
        $rule = ZigoPricingRule::query()->where('carrier', $this->carrierKey($consumer, $provider))
            ->where('customer_segment', $segment)->where('active', true)->whereNull('plan')
            ->whereIn('package_type', [$package, 'all'])
            ->orderByRaw('CASE WHEN package_type = ? THEN 0 ELSE 1 END', [$package])->orderBy('id')->first();
        if (! $rule || (float) $cost <= 0 || (float) $rule->margin_percentage < 0 || (float) $rule->fixed_fee < 0) {
            return null;
        }
        $base = BigDecimal::of($cost);
        $margin = $base->multipliedBy($rule->margin_percentage)->dividedBy('100', 2, RoundingMode::HALF_UP);
        $total = $base->plus($margin)->plus($rule->fixed_fee);
        if ($rule->min_price !== null && $total->isLessThan(BigDecimal::of($rule->min_price))) {
            $total = BigDecimal::of($rule->min_price);
        }

        return ['rule_id' => $rule->id, 'provider_cost' => $cost,
            'customer_price' => (string) $total->toScale(2, RoundingMode::HALF_UP),
            'profit_amount' => (string) $total->minus($base)->toScale(2, RoundingMode::HALF_UP)];
    }
}
