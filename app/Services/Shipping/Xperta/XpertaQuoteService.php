<?php

namespace App\Services\Shipping\Xperta;

use App\Models\B2cCotizacion;
use App\Services\ZigoProviderQuoteObservationService;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class XpertaQuoteService
{
    public function __construct(
        private XpertaApiClient $client,
        private XpertaTokenService $tokenService,
        private ZigoProviderQuoteObservationService
            $observationService
    ) {
    }

    public function options(B2cCotizacion $cotizacion): array
    {

        $services = $this->configuredServices();

        if (!is_array($services) || $services === []) {
            throw new RuntimeException(
                'No hay servicios configurados en XPERTA_SERVICES.'
            );
        }

        $options = [];

        foreach ($services as $service) {
            try {
                $options[] = $this->quote($cotizacion, $service);
            } catch (Throwable $exception) {
                $failure = $this->failureContext($service, $exception);
                Log::warning('Xperta rechazó un servicio de cotización', [
                    'cotizacion_id' => $cotizacion->id,
                ] + $failure);
            }
        }

        if ($options === []) {
            throw new RuntimeException(
                'Xperta no devolvió cotizaciones para los servicios configurados.'
            );
        }

        return $options;
    }

    public function quote(
        B2cCotizacion $cotizacion,
        string $service
    ): array {
        $service = trim(strtolower($service));

        if ($service === '') {
            throw new RuntimeException(
                'El servicio de Xperta es obligatorio.'
            );
        }

        $dimensions = [
            0.0,
            0.0,
            0.0,
        ];

        try {
            [$length, $width, $height] =
                $this->parseDimensions(
                    $cotizacion
                );

            $dimensions = [
                $length,
                $width,
                $height,
            ];

            $path = $this->client->resolvePath(
                (string) config(
                    'services.xperta.quote_path',
                    '/api/v1/empresas/{corporativo}/ltds/'
                    . '{ltd}/servicios/{service}/cotizaciones'
                ),
                [
                    'corporativo' => config('services.xperta.corporativo'),
                    'empresa' => config('services.xperta.corporativo'),
                    'ltd' =>
                        config(
                            'services.xperta.ltd',
                            'estafeta'
                        ),
                    'service' => $service,
                ]
            );

            $declaredValue = config(
                'services.xperta'
                . '.include_declared_value_in_quote',
                false
            )
                ? (float) (
                    $cotizacion->valor_declarado
                    ?? 0
                )
                : 0.0;

            $result = $this->client->sendWithMeta(
                (string) config(
                    'services.xperta.quote_method',
                    'GET'
                ),
                $path,
                [
                    'token' =>
                        $this->tokenService->encodedToken(),
                    'peso' => (float) (
                        $cotizacion->peso_real
                        ?: $cotizacion->peso
                    ),
                    'largo' => $length,
                    'ancho' => $width,
                    'alto' => $height,
                    'cp' => substr(
                        (string) $cotizacion->cp_origen,
                        0,
                        5
                    ),
                    'cp_d' => substr(
                        (string) $cotizacion->cp_destino,
                        0,
                        5
                    ),
                    'valor_declarado' => round(
                        $declaredValue,
                        2
                    ),
                ],
                $this->providerHeaders(),
                true
            );

            $data = data_get(
                $result,
                'data.data.0'
            );

            if (!is_array($data)) {
                throw new RuntimeException(
                    'Xperta no devolvió data[0] '
                    . 'para la cotización.'
                );
            }

            $total = round(
                (float) ($data['total'] ?? 0),
                2
            );

            if ($total <= 0) {
                throw new RuntimeException(
                    'Xperta no devolvió un total válido '
                    . "para {$service}."
                );
            }

            $extendedAreaAmount = round(
                (float) ($data['costo_ae'] ?? 0),
                2
            );

            $this->observationService->recordSuccess(
                $cotizacion,
                $service,
                $data,
                $total,
                $dimensions
            );

            Log::info(
                'ZIGO Provider Rate - Xperta quote',
                [
                    'cotizacion_id' =>
                        $cotizacion->id,
                    'service' => $service,
                    'http_status' => $result['http_status'],
                    'provider_code' => 'XPERTA_QUOTE_OK',
                    'provider_message_code' => 'success',
                    'duration_ms' => $result['duration_ms'],
                    'success' => true,
                    'origin_zip' => substr(
                        (string) $cotizacion->cp_origen,
                        0,
                        5
                    ),
                    'destination_zip' => substr(
                        (string) $cotizacion->cp_destino,
                        0,
                        5
                    ),
                    'base_price' => $total,
                    'extended_area' =>
                        $extendedAreaAmount > 0,
                    'extended_area_amount' =>
                        $extendedAreaAmount,
                ]
            );

            return [
                'logistico' => 'Estafeta',
                'logo' => 'img/estafeta.png',
                'servicio' =>
                    $this->serviceLabel($service),
                'service_code' => $service,
                'entrega' =>
                    $this->deliveryLabel($service),
                'base_price' => $total,
                'provider_source' => 'xperta',
                'correlation_id' => $result['correlation_id'],
                'extended_area' =>
                    $extendedAreaAmount > 0,
                'extended_area_amount' =>
                    $extendedAreaAmount,
                'provider_breakdown' => [
                    'costo' => round(
                        (float) ($data['costo'] ?? 0),
                        2
                    ),
                    'kgs_extras' =>
                        (float) (
                            $data['kgs_extras']
                            ?? 0
                        ),
                    'costo_kgs_extras' => round(
                        (float) (
                            $data['costo_kgs_extras']
                            ?? 0
                        ),
                        2
                    ),
                    'costo_seguro' => round(
                        (float) (
                            $data['costo_seguro']
                            ?? 0
                        ),
                        2
                    ),
                    'costo_ae' =>
                        $extendedAreaAmount,
                    'sub_total' => round(
                        (float) (
                            $data['sub_total']
                            ?? 0
                        ),
                        2
                    ),
                    'total' => $total,
                ],
            ];
        } catch (Throwable $exception) {
            $this->observationService->recordFailure(
                $cotizacion,
                $service,
                $exception,
                $dimensions
            );

            throw $exception;
        }
    }

    private function parseDimensions(
        B2cCotizacion $cotizacion
    ): array {
        if (
            strtolower((string) $cotizacion->tipo_envio) === 'sobre'
            || !$cotizacion->medidas
        ) {
            return [1.0, 1.0, 1.0];
        }

        $parts = preg_split(
            '/x|\*|,|;|\s+/',
            strtolower((string) $cotizacion->medidas)
        );

        $parts = array_values(
            array_filter(
                $parts,
                fn ($value) => $value !== ''
            )
        );

        if (count($parts) < 3) {
            throw new RuntimeException(
                'Las dimensiones no tienen el formato largo x ancho x alto.'
            );
        }

        return [
            max((float) $parts[0], 0.1),
            max((float) $parts[1], 0.1),
            max((float) $parts[2], 0.1),
        ];
    }

    private function serviceLabel(string $service): string
    {
        return match ($service) {
            'terrestre' => 'Terrestre',
            'diasig' => 'Día siguiente',
            default => ucfirst(str_replace('_', ' ', $service)),
        };
    }

    private function deliveryLabel(string $service): string
    {
        return match ($service) {
            'terrestre' => '2 a 5 días hábiles',
            'diasig' => 'Día hábil siguiente',
            default => 'Entrega según cobertura',
        };
    }

    private function providerHeaders(): array
    {
        return [
            'Corporativo' => (string) config(
                'services.xperta.corporativo'
            ),
            'x-api-key' => (string) config('services.xperta.api_key'),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    /** @return list<string> */
    private function configuredServices(): array
    {
        $configured = config('services.xperta.services', []);

        if (!is_array($configured)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($service): string => strtolower(trim((string) $service)),
            $configured
        ), static fn (string $service): bool => $service !== '')));
    }

    /** @return array<string, mixed> */
    private function failureContext(string $service, Throwable $exception): array
    {
        $metadata = $exception instanceof XpertaProviderException
            ? $exception->diagnosticMetadata
            : [];

        return [
            'service' => $service,
            'http_status' => $metadata['http_status'] ?? null,
            'provider_code' => $exception instanceof XpertaProviderException
                ? $exception->errorCode
                : 'XPERTA_QUOTE_FAILED',
            'provider_message_code' => $metadata['provider_message_code'] ?? null,
            'duration_ms' => $metadata['duration_ms'] ?? null,
            'success' => false,
        ];
    }
}
