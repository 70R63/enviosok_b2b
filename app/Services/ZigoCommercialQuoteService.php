<?php

namespace App\Services;

use App\Models\B2cCotizacion;
use App\Services\Pricing\ZigoCommercialPricingEngine;
use RuntimeException;
use Throwable;

class ZigoCommercialQuoteService
{
    public function calculate(
        B2cCotizacion $cotizacion,
        array $option,
        array $context = []
    ): array {
        $carrier = strtoupper(
            trim(
                (string) (
                    $option['logistico']
                    ?? $option['carrier']
                    ?? 'ESTAFETA'
                )
            )
        );

        $service = trim(
            (string) (
                $option['servicio']
                ?? $option['service']
                ?? ''
            )
        );

        if ($service === '') {
            throw new RuntimeException(
                'La opción logística no contiene servicio.'
            );
        }

        $providerQuotedPrice = round(
            (float) (
                $option['base_price']
                ?? $option['precio_base']
                ?? 0
            ),
            2
        );

        $source = strtolower(
            trim(
                (string) (
                    $context['base_rate_source']
                    ?? config(
                        'zigo_commercial_pricing'
                        . '.base_rate_source',
                        'provider'
                    )
                )
            )
        );

        if (
            !in_array(
                $source,
                ['provider', 'contract'],
                true
            )
        ) {
            throw new RuntimeException(
                "Fuente de tarifa no válida: {$source}."
            );
        }

        $selectedBasePrice =
            $providerQuotedPrice;

        $contract = null;
        $fallbackReason = null;
        $effectiveSource = $source;

        if ($source === 'contract') {
            try {
                $contract =
                    app(
                        ZigoContractRateService::class
                    )->calculate([
                        'carrier' => $carrier,
                        'service' => $service,
                        'weight_kg' =>
                            $this->billableWeight(
                                $cotizacion
                            ),
                    ]);

                $selectedBasePrice = round(
                    (float) $contract[
                        'provider_base_price'
                    ],
                    2
                );
            } catch (Throwable $exception) {
                $allowFallback = (bool) (
                    $context[
                        'contract_fallback_to_provider'
                    ]
                    ?? config(
                        'zigo_commercial_pricing'
                        . '.contract_fallback_to_provider',
                        true
                    )
                );

                if (
                    !$allowFallback
                    || $providerQuotedPrice <= 0
                ) {
                    throw $exception;
                }

                $effectiveSource = 'provider';
                $fallbackReason =
                    $exception->getMessage();
            }
        }

        if ($selectedBasePrice <= 0) {
            throw new RuntimeException(
                'El costo base seleccionado '
                . 'debe ser mayor a cero.'
            );
        }

        $pricingContext = [
            'carrier' => $carrier,
            'customer_segment' => $context['customer_segment'] ?? 'anonymous',
            'plan' => $context['plan'] ?? null,
            'package_type' => $context['package_type'] ?? 'sobre',
            'base_price' => $selectedBasePrice,
            'crm_client_id' => $context['crm_client_id'] ?? null,
            'api_client_id' => $context['api_client_id'] ?? null,
            'user_id' => $context['user_id'] ?? null,
        ];
        $conceptPricing = null;
        if (!empty($option['provider_breakdown'])) {
            $conceptPricing = app(ZigoCommercialPricingEngine::class)->calculate(
                (array) $option['provider_breakdown'], $pricingContext,
                strtolower((string) ($option['service_code'] ?? $service)),
                (string) $pricingContext['customer_segment'],
                (string) $pricingContext['package_type'],
                (float) config('zigo_commercial_pricing.vat_rate', 0.16)
            );
        }
        $pricing = $conceptPricing
            ? $this->legacyCompatiblePricing($conceptPricing, $pricingContext)
            : app(ZigoPricingService::class)->calculate($pricingContext);

        return [
            'carrier' => $carrier,
            'service' => $service,

            'requested_base_rate_source' =>
                $source,
            'effective_base_rate_source' =>
                $effectiveSource,

            'provider_quoted_price' =>
                $providerQuotedPrice,
            'selected_base_price' =>
                $selectedBasePrice,

            'provider_source' =>
                $option['provider_source']
                ?? null,

            'contract' => $contract,
            'contract_rate_id' =>
                $contract['contract_rate_id']
                ?? null,
            'contract_total_price' =>
                $contract['total_price']
                ?? null,

            'fallback_reason' =>
                $fallbackReason,

            'pricing' => $pricing,
            'final_price' =>
                $pricing['final_price'],
            'profit_amount' =>
                $pricing['profit_amount'],
            'concept_pricing' => $conceptPricing,
        ];
    }

    private function legacyCompatiblePricing(array $result, array $context): array
    {
        $operational = array_sum($result['operational_breakdown']);
        $profit = round($result['commercial_subtotal'] - $operational, 2);
        return [
            'carrier' => $context['carrier'], 'customer_segment' => $context['customer_segment'],
            'plan' => $context['plan'], 'package_type' => $context['package_type'],
            'base_price' => round($operational, 2),
            'pricing_rule_id' => data_get($result, 'applied_rules.base.id'),
            'pricing_rule_name' => data_get($result, 'applied_rules.base.name', 'Reglas por concepto'),
            'margin_percentage' => 0, 'fixed_fee' => 0, 'margin_amount' => $profit,
            'adjustment_id' => null, 'adjustment_name' => null, 'adjustment_type' => null,
            'adjustment_value' => null, 'adjustment_amount' => 0,
            'client_pricing_rule_id' => null, 'client_pricing_rule_name' => null,
            'discount_type' => null, 'discount_value' => null, 'discount_amount' => 0,
            'final_price' => $result['customer_total'], 'profit_amount' => $profit,
        ];
    }

    private function billableWeight(
        B2cCotizacion $cotizacion
    ): float {
        $weight = (float) (
            $cotizacion->peso_facturable
            ?: $cotizacion->peso
        );

        if ($weight <= 0) {
            throw new RuntimeException(
                'La cotización no tiene '
                . 'un peso facturable válido.'
            );
        }

        return round(
            $weight,
            3
        );
    }
}
