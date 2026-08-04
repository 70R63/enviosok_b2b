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

        $frequencyByService = $this->frequencyByService(
            (array) ($coverage['response'] ?? [])
        );

        return array_map(function (array $option) use ($quote, $frequencyByService) {
            $breakdown = (array) ($option['provider_breakdown'] ?? []);
            $serviceCode = $this->serviceCode(
                (string) ($option['service_code'] ?? $option['servicio'] ?? '')
            );
            $frequency = $frequencyByService[$serviceCode] ?? [];

            $option['service_code'] = $serviceCode;
            $option['provider_source'] = 'xperta';
            $option['quote_source'] = 'xperta_estafeta';
            $option['carrier'] = 'estafeta';
            $option['estimated_delivery_date'] = $frequency['estimated_delivery_date'] ?? null;
            $option['zone_code'] = $frequency['zone_code'] ?? null;
            $option['periodicity_name'] = $frequency['periodicity_name'] ?? null;
            $option['operating_days'] = $frequency['operating_days'] ?? [];
            $option['is_reexpedition'] = (bool) ($frequency['is_reexpedition'] ?? false);
            $option['is_ocurre'] = (bool) ($frequency['is_ocurre'] ?? false);
            $option['restriction'] = $frequency['restriction'] ?? null;
            $option['restriction_description'] = $frequency['restriction_description'] ?? null;
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

    private function frequencyByService(array $response): array
    {
        $services = [];
        $this->collectFrequencyServices($response, $services);

        $mapped = [];
        foreach ($services as $service) {
            $code = $this->serviceCode((string) ($service['name'] ?? ''));
            if (!in_array($code, ['terrestre', 'diasig'], true)) {
                continue;
            }

            $mapped[$code] = [
                'estimated_delivery_date' => $service['estimatedDeliveryDate'] ?? null,
                'zone_code' => $service['zoneCode'] ?? null,
                'periodicity_name' => $service['periodicityName'] ?? null,
                'operating_days' => $this->operatingDays($service),
                'is_reexpedition' => (bool) ($service['isReexpedition'] ?? false),
                'is_ocurre' => (bool) ($service['isOcurre'] ?? false),
                'restriction' => $service['restriction'] ?? null,
                'restriction_description' => $service['restrictionDescription'] ?? null,
            ];
        }

        return $mapped;
    }

    private function collectFrequencyServices(array $node, array &$services): void
    {
        if (array_key_exists('name', $node)
            && (array_key_exists('estimatedDeliveryDate', $node)
                || array_key_exists('periodicityName', $node)
                || array_key_exists('zoneCode', $node))) {
            $services[] = $node;
        }

        foreach ($node as $value) {
            if (is_array($value)) {
                $this->collectFrequencyServices($value, $services);
            }
        }
    }

    private function serviceCode(string $name): string
    {
        $normalized = strtolower(trim(str_replace(['í', '.'], ['i', ''], $name)));

        return match ($normalized) {
            'terrestre' => 'terrestre',
            'dia sig', 'dia siguiente', 'diasig' => 'diasig',
            default => $normalized,
        };
    }

    private function operatingDays(array $service): array
    {
        $days = [
            'isMonday' => 'Lun',
            'isTuesday' => 'Mar',
            'isWednesday' => 'Mié',
            'isThursday' => 'Jue',
            'isFriday' => 'Vie',
            'isSaturday' => 'Sáb',
            'isSunday' => 'Dom',
        ];

        return array_values(array_filter(array_map(
            static fn (string $key, string $label): ?string =>
                (bool) ($service[$key] ?? false) ? $label : null,
            array_keys($days),
            array_values($days)
        )));
    }
}
