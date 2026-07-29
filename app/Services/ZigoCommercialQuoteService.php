<?php

namespace App\Services;

use App\Models\B2cCotizacion;
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

        $pricing =
            app(ZigoPricingService::class)
                ->calculate([
                    'carrier' => $carrier,
                    'customer_segment' =>
                        $context['customer_segment']
                        ?? 'anonymous',
                    'plan' =>
                        $context['plan']
                        ?? null,
                    'package_type' =>
                        $context['package_type']
                        ?? 'sobre',
                    'base_price' =>
                        $selectedBasePrice,
                    'crm_client_id' =>
                        $context['crm_client_id']
                        ?? null,
                    'api_client_id' =>
                        $context['api_client_id']
                        ?? null,
                    'user_id' =>
                        $context['user_id']
                        ?? null,
                ]);

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
