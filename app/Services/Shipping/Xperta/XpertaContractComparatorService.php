<?php

namespace App\Services\Shipping\Xperta;

use App\Models\B2cCotizacion;
use App\Services\ZigoContractRateService;
use App\Services\ZigoPricingService;
use RuntimeException;

class XpertaContractComparatorService
{
    public function __construct(
        private XpertaQuoteService $xpertaQuoteService,
        private ZigoContractRateService $contractRateService,
        private ZigoPricingService $pricingService
    ) {
    }

    public function previewContract(
        B2cCotizacion $cotizacion,
        string $service,
        array $context = []
    ): array {
        $normalizedService = $this->normalizeService($service);
        $carrier = 'ESTAFETA';
        $weight = $this->billableWeight($cotizacion);

        $contract = $this->contractRateService->calculate([
            'carrier' => $carrier,
            'service' => $normalizedService['contract_label'],
            'weight_kg' => $weight,
        ]);

        $commercial = $this->pricingService->calculate([
            'carrier' => $carrier,
            'customer_segment' => $context['customer_segment'] ?? 'b2c',
            'plan' => $context['plan'] ?? null,
            'package_type' => $context['package_type'] ?? $this->packageType($cotizacion),
            'base_price' => $contract['provider_base_price'],
            'crm_client_id' => $context['crm_client_id'] ?? null,
            'api_client_id' => $context['api_client_id'] ?? null,
            'user_id' => $context['user_id'] ?? $cotizacion->user_id,
        ]);

        return [
            'mode' => 'contract_preview',
            'cotizacion_id' => $cotizacion->exists ? $cotizacion->id : null,
            'carrier' => $carrier,
            'service_code' => $normalizedService['xperta_code'],
            'service_name' => $normalizedService['contract_label'],
            'physical_weight_kg' => $this->physicalWeight($cotizacion),
            'billable_weight_kg' => $weight,
            'package_type' => $context['package_type'] ?? $this->packageType($cotizacion),
            'customer_segment' => $context['customer_segment'] ?? 'b2c',
            'contract' => $contract,
            'commercial' => $commercial,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    public function compare(
        B2cCotizacion $cotizacion,
        string $service,
        array $context = []
    ): array {
        $this->assertXpertaAvailable($context);

        $normalizedService = $this->normalizeService($service);
        $contractPreview = $this->previewContract(
            $cotizacion,
            $service,
            $context
        );

        $xperta = $this->xpertaQuoteService->quote(
            $cotizacion,
            $normalizedService['xperta_code']
        );

        $xpertaBase = round((float) ($xperta['base_price'] ?? 0), 2);
        $contractBase = round(
            (float) data_get(
                $contractPreview,
                'contract.provider_base_price',
                0
            ),
            2
        );

        if ($xpertaBase <= 0 || $contractBase <= 0) {
            throw new RuntimeException(
                'Xperta o contrato no devolvieron un costo base válido.'
            );
        }

        $xpertaCommercial = $this->pricingService->calculate([
            'carrier' => 'ESTAFETA',
            'customer_segment' => $context['customer_segment'] ?? 'b2c',
            'plan' => $context['plan'] ?? null,
            'package_type' => $context['package_type'] ?? $this->packageType($cotizacion),
            'base_price' => $xpertaBase,
            'crm_client_id' => $context['crm_client_id'] ?? null,
            'api_client_id' => $context['api_client_id'] ?? null,
            'user_id' => $context['user_id'] ?? $cotizacion->user_id,
        ]);

        $difference = round($xpertaBase - $contractBase, 2);
        $absoluteDifference = round(abs($difference), 2);
        $differencePercentage = round(
            ($difference / $contractBase) * 100,
            2
        );

        $commercialDifference = round(
            (float) $xpertaCommercial['final_price']
            - (float) data_get(
                $contractPreview,
                'commercial.final_price',
                0
            ),
            2
        );

        return [
            'mode' => 'xperta_vs_contract',
            'cotizacion_id' => $cotizacion->exists ? $cotizacion->id : null,
            'carrier' => 'ESTAFETA',
            'service_code' => $normalizedService['xperta_code'],
            'service_name' => $normalizedService['contract_label'],
            'environment' => config(
                'services.xperta.environment',
                'sandbox'
            ),
            'physical_weight_kg' => $this->physicalWeight($cotizacion),
            'billable_weight_kg' => $contractPreview['billable_weight_kg'],
            'package_type' => $contractPreview['package_type'],
            'customer_segment' => $contractPreview['customer_segment'],
            'xperta' => [
                'provider_source' => $xperta['provider_source'] ?? 'xperta',
                'base_price' => $xpertaBase,
                'extended_area' => (bool) ($xperta['extended_area'] ?? false),
                'extended_area_amount' => round(
                    (float) ($xperta['extended_area_amount'] ?? 0),
                    2
                ),
                'provider_breakdown' => $xperta['provider_breakdown'] ?? [],
                'commercial' => $xpertaCommercial,
            ],
            'contract' => [
                'base_price' => $contractBase,
                'rate' => $contractPreview['contract'],
                'commercial' => $contractPreview['commercial'],
            ],
            'difference' => [
                'xperta_minus_contract' => $difference,
                'absolute_amount' => $absoluteDifference,
                'percentage_vs_contract' => $differencePercentage,
                'commercial_xperta_minus_contract' => $commercialDifference,
                'lower_base_source' => $this->lowerSource(
                    $xpertaBase,
                    $contractBase
                ),
                'potential_base_saving' => $absoluteDifference,
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    private function assertXpertaAvailable(array $context): void
    {
        if (!config('services.xperta.enabled', false)) {
            throw new RuntimeException(
                'XPERTA_ENABLED está desactivado.'
            );
        }

        $environment = strtolower(
            trim(
                (string) config(
                    'services.xperta.environment',
                    'sandbox'
                )
            )
        );

        if (
            $environment !== 'sandbox'
            && !(bool) ($context['allow_production'] ?? false)
        ) {
            throw new RuntimeException(
                'El comparador solo permite Xperta sandbox.'
            );
        }
    }

    private function normalizeService(string $service): array
    {
        $normalized = mb_strtolower(trim($service));
        $normalized = str_replace(
            ['í', 'ó', 'á', 'é', 'ú'],
            ['i', 'o', 'a', 'e', 'u'],
            $normalized
        );

        if (in_array($normalized, ['terrestre', 'ground', 'standard'], true)) {
            return [
                'xperta_code' => 'terrestre',
                'contract_label' => 'Terrestre',
            ];
        }

        if (
            in_array(
                $normalized,
                [
                    'diasig',
                    'dia sig',
                    'dia siguiente',
                    'next day',
                    'next_day',
                ],
                true
            )
        ) {
            return [
                'xperta_code' => 'diasig',
                'contract_label' => 'Día siguiente',
            ];
        }

        throw new RuntimeException(
            'Servicio no reconocido: '
            . $service
            . '. Usa terrestre o diasig.'
        );
    }

    private function physicalWeight(B2cCotizacion $cotizacion): float
    {
        return round(
            (float) (
                $cotizacion->peso_real
                ?: $cotizacion->peso
            ),
            3
        );
    }

    private function billableWeight(B2cCotizacion $cotizacion): float
    {
        $weight = (float) (
            $cotizacion->peso_facturable
                ?: $cotizacion->peso_real
                ?: $cotizacion->peso
        );

        if ($weight <= 0) {
            throw new RuntimeException(
                'La cotización no tiene peso facturable válido.'
            );
        }

        return round($weight, 3);
    }

    private function packageType(B2cCotizacion $cotizacion): string
    {
        $type = mb_strtolower(
            trim(
                (string) (
                    $cotizacion->tipo_envio
                    ?: 'sobre'
                )
            )
        );

        return str_contains($type, 'caja')
            || str_contains($type, 'paquete')
                ? 'caja'
                : 'sobre';
    }

    private function lowerSource(float $xperta, float $contract): string
    {
        if (abs($xperta - $contract) < 0.01) {
            return 'equal';
        }

        return $xperta < $contract
            ? 'xperta'
            : 'contract';
    }
}
