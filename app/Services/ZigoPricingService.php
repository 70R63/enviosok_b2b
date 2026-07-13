<?php

namespace App\Services;

use App\Models\ZigoPricingRule;
use App\Models\ZigoPricingAdjustment;
use App\Models\ZigoClientPricingRule;
use RuntimeException;

class ZigoPricingService
{
    public function calculate(array $data): array
    {
        $carrier = strtoupper($data['carrier'] ?? 'ESTAFETA');
        $segment = strtolower($data['customer_segment'] ?? 'anonymous');
        $plan = $data['plan'] ?? null;
        $packageType = strtolower($data['package_type'] ?? 'sobre');
        $basePrice = round((float) ($data['base_price'] ?? 0), 2);

        $crmClientId = $data['crm_client_id'] ?? null;
        $apiClientId = $data['api_client_id'] ?? null;
        $userId = $data['user_id'] ?? null;

        if ($basePrice <= 0) {
            throw new RuntimeException('La tarifa base proveedor no es válida.');
        }

        $pricingRule = $this->findPricingRule($carrier, $segment, $plan, $packageType);

        if (!$pricingRule) {
            throw new RuntimeException("No existe regla base activa para {$segment} / {$packageType}.");
        }

        $marginPercentage = round((float) $pricingRule->margin_percentage, 2);
        $fixedFee = round((float) $pricingRule->fixed_fee, 2);
        $marginAmount = round($basePrice * ($marginPercentage / 100), 2);

        $priceAfterMargin = round($basePrice + $marginAmount + $fixedFee, 2);

        if ($pricingRule->min_price && $priceAfterMargin < (float) $pricingRule->min_price) {
            $priceAfterMargin = round((float) $pricingRule->min_price, 2);
        }

        $adjustment = $this->findAdjustment($carrier, $segment, $packageType);
        $adjustmentAmount = 0;
        $adjustmentType = null;
        $adjustmentValue = null;

        if ($adjustment) {
            $adjustmentType = $adjustment->adjustment_type;
            $adjustmentValue = round((float) $adjustment->adjustment_value, 2);

            $adjustmentAmount = $this->calculateAdjustmentAmount(
                $priceAfterMargin,
                $adjustmentType,
                $adjustmentValue
            );
        }

        $priceAfterAdjustment = round($priceAfterMargin + $adjustmentAmount, 2);

        $clientRule = $this->findClientPricingRule(
            $segment,
            $packageType,
            $crmClientId,
            $apiClientId,
            $userId
        );

        $discountAmount = 0;
        $discountType = null;
        $discountValue = null;

        if ($clientRule) {
            $discountType = $clientRule->discount_type;
            $discountValue = round((float) $clientRule->discount_value, 2);

            $discountAmount = $this->calculateDiscountAmount(
                $priceAfterAdjustment,
                $discountType,
                $discountValue
            );
        }

        $finalPrice = round($priceAfterAdjustment - $discountAmount, 2);

        if ($finalPrice < $basePrice) {
            $finalPrice = $basePrice;
        }

        $profitAmount = round($finalPrice - $basePrice, 2);

        return [
            'carrier' => $carrier,
            'customer_segment' => $segment,
            'plan' => $plan,
            'package_type' => $packageType,

            'base_price' => $basePrice,

            'pricing_rule_id' => $pricingRule->id,
            'pricing_rule_name' => $pricingRule->name,
            'margin_percentage' => $marginPercentage,
            'fixed_fee' => $fixedFee,
            'margin_amount' => $marginAmount,

            'adjustment_id' => $adjustment?->id,
            'adjustment_name' => $adjustment?->name,
            'adjustment_type' => $adjustmentType,
            'adjustment_value' => $adjustmentValue,
            'adjustment_amount' => $adjustmentAmount,

            'client_pricing_rule_id' => $clientRule?->id,
            'client_pricing_rule_name' => $clientRule?->name,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'discount_amount' => $discountAmount,

            'final_price' => $finalPrice,
            'profit_amount' => $profitAmount,
        ];
    }

    private function findPricingRule(string $carrier, string $segment, ?string $plan, string $packageType): ?ZigoPricingRule
    {
        return ZigoPricingRule::query()
            ->where('carrier', $carrier)
            ->where('customer_segment', $segment)
            ->where('active', true)
            ->where(function ($query) use ($plan) {
                $query->whereNull('plan');

                if ($plan) {
                    $query->orWhere('plan', $plan);
                }
            })
            ->where(function ($query) use ($packageType) {
                $query->where('package_type', $packageType)
                    ->orWhere('package_type', 'all');
            })
            ->orderByRaw("CASE WHEN package_type = ? THEN 0 ELSE 1 END", [$packageType])
            ->orderByRaw("CASE WHEN plan IS NOT NULL THEN 0 ELSE 1 END")
            ->first();
    }

    private function findAdjustment(string $carrier, string $segment, string $packageType): ?ZigoPricingAdjustment
    {
        return ZigoPricingAdjustment::query()
            ->where('carrier', $carrier)
            ->where('active', true)
            ->where(function ($query) use ($segment) {
                $query->where('customer_segment', $segment)
                    ->orWhere('customer_segment', 'all');
            })
            ->where(function ($query) use ($packageType) {
                $query->where('package_type', $packageType)
                    ->orWhere('package_type', 'all');
            })
            ->get()
            ->filter(fn ($adjustment) => $adjustment->isAvailable())
            ->sortBy(function ($adjustment) use ($segment, $packageType) {
                $score = 0;

                if ($adjustment->customer_segment === $segment) {
                    $score += 10;
                }

                if ($adjustment->package_type === $packageType) {
                    $score += 5;
                }

                return -$score;
            })
            ->first();
    }

    private function findClientPricingRule(
        string $segment,
        string $packageType,
        ?int $crmClientId,
        ?int $apiClientId,
        ?int $userId
    ): ?ZigoClientPricingRule {
        return ZigoClientPricingRule::query()
            ->where('active', true)
            ->where(function ($query) use ($segment) {
                $query->whereNull('customer_segment')
                    ->orWhere('customer_segment', $segment);
            })
            ->where(function ($query) use ($packageType) {
                $query->where('package_type', $packageType)
                    ->orWhere('package_type', 'all');
            })
            ->where(function ($query) use ($crmClientId, $apiClientId, $userId) {
                if ($crmClientId) {
                    $query->orWhere('crm_client_id', $crmClientId);
                }

                if ($apiClientId) {
                    $query->orWhere('api_client_id', $apiClientId);
                }

                if ($userId) {
                    $query->orWhere('user_id', $userId);
                }
            })
            ->get()
            ->filter(fn ($rule) => $rule->isAvailable())
            ->first();
    }

    private function calculateAdjustmentAmount(float $price, string $type, float $value): float
    {
        return match ($type) {
            'surcharge_percentage' => round($price * ($value / 100), 2),
            'surcharge_fixed' => round($value, 2),
            'discount_percentage' => round(-1 * ($price * ($value / 100)), 2),
            'discount_fixed' => round(-1 * $value, 2),
            default => 0,
        };
    }

    private function calculateDiscountAmount(float $price, string $type, float $value): float
    {
        return match ($type) {
            'percentage' => round($price * ($value / 100), 2),
            'fixed' => round($value, 2),
            default => 0,
        };
    }
}