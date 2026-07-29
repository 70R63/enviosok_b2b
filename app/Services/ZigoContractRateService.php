<?php

namespace App\Services;

use App\Models\ZigoContractRate;
use Illuminate\Support\Str;
use RuntimeException;

class ZigoContractRateService
{
    public function calculate(array $data): array
    {
        $carrier = strtoupper(
            trim(
                (string) (
                    $data['carrier']
                    ?? 'ESTAFETA'
                )
            )
        );

        $serviceCode = $this->normalizeServiceCode(
            (string) (
                $data['service_code']
                ?? $data['service']
                ?? ''
            )
        );

        $weightKg = round(
            (float) (
                $data['weight_kg']
                ?? $data['peso']
                ?? 0
            ),
            3
        );

        if ($weightKg <= 0) {
            throw new RuntimeException(
                'El peso facturable debe ser mayor a cero.'
            );
        }

        $rate = $this->findRate(
            $carrier,
            $serviceCode,
            $data['date'] ?? null
        );

        if (!$rate) {
            throw new RuntimeException(
                'No existe una tarifa contractual activa '
                . "para {$carrier} / {$serviceCode}."
            );
        }

        $includedWeight = round(
            (float) $rate->included_weight_kg,
            3
        );

        $basePrice = round(
            (float) $rate->base_price,
            2
        );

        $additionalUnit = round(
            (float) $rate
                ->additional_weight_unit_kg,
            3
        );

        $additionalUnitPrice = round(
            (float) $rate
                ->additional_weight_price,
            2
        );

        if ($additionalUnit <= 0) {
            throw new RuntimeException(
                'La unidad de peso adicional '
                . 'debe ser mayor a cero.'
            );
        }

        $additionalWeight = max(
            0,
            round(
                $weightKg - $includedWeight,
                3
            )
        );

        $additionalUnits = $additionalWeight > 0
            ? (int) ceil(
                (
                    $additionalWeight
                    / $additionalUnit
                )
                - 0.0000001
            )
            : 0;

        $additionalAmount = round(
            $additionalUnits
            * $additionalUnitPrice,
            2
        );

        $netPrice = round(
            $basePrice + $additionalAmount,
            2
        );

        $taxPercentage = round(
            (float) $rate->tax_percentage,
            2
        );

        $taxAmount = round(
            $netPrice
            * ($taxPercentage / 100),
            2
        );

        $totalPrice = round(
            $netPrice + $taxAmount,
            2
        );

        return [
            'contract_rate_id' => $rate->id,
            'contract_rate_name' => $rate->name,
            'carrier' => $rate->carrier,
            'service_code' => $rate->service_code,
            'service_name' => $rate->service_name,
            'currency' => $rate->currency,

            'weight_kg' => $weightKg,
            'included_weight_kg' =>
                $includedWeight,
            'additional_weight_kg' =>
                $additionalWeight,
            'additional_weight_unit_kg' =>
                $additionalUnit,
            'additional_units' =>
                $additionalUnits,

            'base_price' => $basePrice,
            'additional_weight_price' =>
                $additionalUnitPrice,
            'additional_amount' =>
                $additionalAmount,

            'net_price' => $netPrice,
            'tax_percentage' =>
                $taxPercentage,
            'tax_amount' => $taxAmount,
            'total_price' => $totalPrice,

            /*
             * Este valor alimentará ZigoPricingService
             * en la siguiente fase comercial.
             */
            'provider_base_price' =>
                $totalPrice,

            'valid_from' =>
                optional($rate->valid_from)
                    ->toDateString(),
            'valid_to' =>
                optional($rate->valid_to)
                    ->toDateString(),
            'source' => $rate->source,
        ];
    }

    public function findRate(
        string $carrier,
        string $serviceCode,
        $date = null
    ): ?ZigoContractRate {
        return ZigoContractRate::query()
            ->where(
                'carrier',
                strtoupper(trim($carrier))
            )
            ->where(
                'service_code',
                $this->normalizeServiceCode(
                    $serviceCode
                )
            )
            ->available($date)
            ->orderByRaw(
                'CASE WHEN valid_from IS NULL '
                . 'THEN 1 ELSE 0 END'
            )
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->first();
    }

    public function normalizeServiceCode(
        string $service
    ): string {
        $normalized = strtolower(
            trim(
                Str::ascii($service)
            )
        );

        $normalized = preg_replace(
            '/[^a-z0-9]+/',
            '_',
            $normalized
        );

        $normalized = trim(
            (string) $normalized,
            '_'
        );

        return match ($normalized) {
            'terrestre',
            'ground',
            'standard',
            'estafeta_terrestre' =>
                'TERRESTRE',

            'dia_siguiente',
            'dia_sig',
            'next_day',
            'nextday',
            'estafeta_dia_siguiente' =>
                'DIA_SIGUIENTE',

            default =>
                strtoupper($normalized),
        };
    }
}
