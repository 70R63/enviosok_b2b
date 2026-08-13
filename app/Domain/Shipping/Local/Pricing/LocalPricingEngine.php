<?php

namespace App\Domain\Shipping\Local\Pricing;

use App\Domain\Shipping\Local\Models\{LocalShippingPricingRule,LocalShippingService};
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DomainException;

final class LocalPricingEngine
{
    public function price(LocalShippingService $service, string $packageType, ?int $distanceMeters): LocalPrice
    {
        $rules = $service->pricingRules->where('active', true)->filter(fn($r)=>!$r->valid_from || $r->valid_from->lte(now()))->filter(fn($r)=>!$r->valid_to || $r->valid_to->gte(now()));
        $km = BigDecimal::of((string)($distanceMeters ?? 0))->dividedBy('1000', 3, RoundingMode::HALF_UP);
        $strategy = $service->pricing_strategy;
        if ($strategy === 'FLAT') $rule = $rules->first();
        elseif ($strategy === 'PACKAGE_FLAT') $rule = $rules->firstWhere('package_type', $packageType);
        elseif ($strategy === 'BASE_PLUS_OVERAGE') $rule = $rules->first();
        else $rule = $rules->first(fn($r)=>BigDecimal::of($r->from_km ?? '0')->isLessThanOrEqualTo($km) && ($r->to_km === null || $km->isLessThanOrEqualTo(BigDecimal::of($r->to_km))));
        if (! $rule) throw new DomainException('No hay una tarifa vigente aplicable.');
        $base = BigDecimal::of($rule->amount); $overage = BigDecimal::zero();
        if (in_array($strategy, ['BASE_PLUS_OVERAGE','DISTANCE_TIERS_OVERAGE'], true) && $rule->overage_price_per_km !== null) {
            $limit = BigDecimal::of($rule->included_distance_km ?? $rule->from_km ?? '0');
            if ($km->isGreaterThan($limit)) {
                $extra = $km->minus($limit);
                if ($rule->overage_rounding === 'CEIL') $extra = $extra->toScale(0, RoundingMode::CEILING);
                $overage = $extra->multipliedBy($rule->overage_price_per_km);
            }
        }
        return new LocalPrice($service->id,$service->name,$strategy,$distanceMeters ?? 0,(string)$km,$rule->id,(string)$base->toScale(2),(string)$overage->toScale(2,RoundingMode::HALF_UP),(string)$base->plus($overage)->toScale(2,RoundingMode::HALF_UP),$service->currency,['sla'=>$service->sla_text,'rule'=>$rule->toArray()]);
    }
}
