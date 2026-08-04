<?php

namespace App\Services\Shipping;

use App\Models\B2cCotizacion;
use App\Services\Shipping\Xperta\XpertaFrequencyService;
use App\Services\Shipping\Xperta\XpertaQuoteService;
use RuntimeException;

final class B2cXpertaQuoteFlowService
{
    public function __construct(
        private XpertaFrequencyService $frequency,
        private XpertaQuoteService $quotes
    ) {
    }

    public function options(B2cCotizacion $quote): array
    {
        $this->assertStage();

        $coverage = $this->frequency->check(
            (string) $quote->cp_origen,
            (string) $quote->cp_destino
        );

        if (!($coverage['available'] ?? false)) {
            throw new RuntimeException(
                'La ruta seleccionada no tiene cobertura disponible.'
            );
        }

        return array_map(function (array $option) use ($quote, $coverage) {
            $breakdown = (array) ($option['provider_breakdown'] ?? []);
            $serviceCode = $this->serviceCode(
                (string) ($option['service_code'] ?? $option['servicio'] ?? '')
            );
            if (!isset($coverage['services'][$serviceCode]['estimated_delivery_date'])) {
                throw new RuntimeException(
                    "La frecuencia Xperta no contiene el servicio {$serviceCode}."
                );
            }

            $option['service_code'] = $serviceCode;
            $option['provider_source'] = 'xperta';
            $option['quote_source'] = 'xperta_estafeta';
            $option['carrier'] = 'estafeta';
            $option['estimated_delivery_date'] =
                $coverage['services'][$serviceCode]['estimated_delivery_date'];
            $option['zone_code'] = $coverage['zone_code'];
            $option['periodicity_name'] = $coverage['periodicity_name'];
            $option['operating_days'] = $coverage['operating_days'];
            $option['is_reexpedition'] = $coverage['is_reexpedition'];
            $option['is_ocurre'] = $coverage['is_ocurre'];
            $option['restriction'] = $coverage['restriction'];
            $option['restriction_description'] = $coverage['restriction_description'];
            $option['provider_extended_area_price'] = round((float) ($breakdown['costo_ae'] ?? 0), 2);
            $option['provider_subtotal'] = round((float) ($breakdown['sub_total'] ?? 0), 2);
            $option['provider_total'] = round((float) ($breakdown['total'] ?? $option['base_price'] ?? 0), 2);
            $option['weight_billable'] = round((float) ($quote->peso_facturable ?: $quote->peso), 3);
            $option['dimensions'] = $quote->medidas ?: 'No aplica';
            $option['insurance_enabled'] = (bool) $quote->requiere_seguro_envio;
            $option['request_fingerprint'] = hash('sha256', implode('|', [
                $quote->cp_origen,
                $quote->cp_destino,
                $option['weight_billable'],
                $quote->medidas,
                $serviceCode,
            ]));
            $option['correlation_id'] = $option['correlation_id'] ?? null;
            $option['quote_expires_at'] = now()->addMinutes(
                max(1, (int) config('zigo_b2c_xperta.quote_ttl_minutes', 30))
            );

            return $option;
        }, $this->quotes->options($quote));
    }

    public function assertStage(): void
    {
        if (!config('zigo_b2c_xperta.full_flow_enabled', false)) {
            throw new RuntimeException('El flujo Xperta B2C está desactivado.');
        }

        if (strtolower((string) config('services.xperta.environment')) !== 'stage') {
            throw new RuntimeException('El flujo Xperta B2C solo puede ejecutarse en Stage.');
        }

        if (!config('services.xperta.enabled', false)
            || !config('services.xperta.frequency_enabled', false)) {
            throw new RuntimeException('La configuración Xperta Stage no está completa.');
        }
    }

    private function serviceCode(string $name): string
    {
        $normalized = strtr(mb_strtolower(trim($name)), [
            'á' => 'a', 'é' => 'e', 'í' => 'i',
            'ó' => 'o', 'ú' => 'u', 'ü' => 'u', '.' => '',
        ]);
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        return match ($normalized) {
            'terrestre' => 'terrestre',
            'dia sig', 'dia siguiente', 'diasig' => 'diasig',
            default => $normalized,
        };
    }

}
