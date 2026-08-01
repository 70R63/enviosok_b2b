<?php

namespace App\Services;

use App\Models\B2cCotizacion;
use App\Models\ZigoAgreementService;
use App\Models\ZigoProviderQuoteObservation;
use App\Services\Shipping\Xperta\XpertaExternalMessageSanitizer;
use Illuminate\Support\Facades\Log;
use Throwable;

class ZigoProviderQuoteObservationService
{
    public function recordSuccess(
        B2cCotizacion $cotizacion,
        string $serviceCode,
        array $providerData,
        float $total,
        array $dimensions
    ): ?ZigoProviderQuoteObservation {
        return $this->record(
            cotizacion: $cotizacion,
            serviceCode: $serviceCode,
            dimensions: $dimensions,
            success: true,
            total: $total,
            extendedAreaAmount: round(
                (float) ($providerData['costo_ae'] ?? 0),
                2
            ),
            errorMessage: null,
            providerData: $this->safeProviderData($providerData)
        );
    }

    public function recordFailure(
        B2cCotizacion $cotizacion,
        string $serviceCode,
        Throwable $exception,
        array $dimensions
    ): ?ZigoProviderQuoteObservation {
        return $this->record(
            cotizacion: $cotizacion,
            serviceCode: $serviceCode,
            dimensions: $dimensions,
            success: false,
            total: null,
            extendedAreaAmount: 0.0,
            errorMessage: XpertaExternalMessageSanitizer::sanitize(
                $exception->getMessage()
            ),
            providerData: null
        );
    }

    private function record(
        B2cCotizacion $cotizacion,
        string $serviceCode,
        array $dimensions,
        bool $success,
        ?float $total,
        float $extendedAreaAmount,
        ?string $errorMessage,
        ?array $providerData
    ): ?ZigoProviderQuoteObservation {
        try {
            $agreementService =
                $this->resolveAgreementService(
                    $serviceCode
                );

            if (!$agreementService) {
                Log::warning(
                    'ZIGO Xperta - Servicio sin relación '
                    . 'tarifaria para observación',
                    [
                        'service_code' => $serviceCode,
                    ]
                );

                return null;
            }

            [$length, $width, $height] = array_pad(
                $dimensions,
                3,
                0.0
            );

            return ZigoProviderQuoteObservation::query()
                ->create([
                    'agreement_service_id' =>
                        $agreementService->id,
                    'b2c_cotizacion_id' =>
                        $cotizacion->exists
                            ? $cotizacion->id
                            : null,
                    'provider_source_code' => 'XPERTA',
                    'service_code' => $serviceCode,
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
                    'weight_kg' => round(
                        (float) (
                            $cotizacion->peso_real
                            ?: $cotizacion->peso
                        ),
                        3
                    ),
                    'length_cm' => round(
                        (float) $length,
                        3
                    ),
                    'width_cm' => round(
                        (float) $width,
                        3
                    ),
                    'height_cm' => round(
                        (float) $height,
                        3
                    ),
                    'currency' => 'MXN',
                    'total' => $total,
                    'extended_area_amount' =>
                        $extendedAreaAmount,
                    'success' => $success,
                    'error_message' => $errorMessage,
                    'response_payload' => $providerData,
                    'observed_at' => now(),
                ]);
        } catch (Throwable $exception) {
            Log::warning(
                'ZIGO Xperta - No se pudo guardar '
                . 'la observación tarifaria',
                [
                    'service_code' => $serviceCode,
                    'message' => XpertaExternalMessageSanitizer::sanitize(
                        $exception->getMessage()
                    ),
                ]
            );

            return null;
        }
    }

    private function safeProviderData(array $providerData): array
    {
        $safe = [];

        foreach ([
            'costo',
            'kgs_extras',
            'costo_kgs_extras',
            'costo_seguro',
            'costo_ae',
            'sub_total',
            'total',
        ] as $field) {
            if (
                isset($providerData[$field])
                && is_numeric($providerData[$field])
            ) {
                $safe[$field] = (float) $providerData[$field];
            }
        }

        return $safe;
    }

    private function resolveAgreementService(
        string $serviceCode
    ): ?ZigoAgreementService {
        return ZigoAgreementService::query()
            ->where('active', true)
            ->whereRaw(
                'LOWER(external_service_code) = ?',
                [
                    strtolower(
                        trim($serviceCode)
                    ),
                ]
            )
            ->whereHas(
                'agreement',
                fn ($query) =>
                    $query
                        ->where('active', true)
                        ->whereHas(
                            'source',
                            fn ($sourceQuery) =>
                                $sourceQuery
                                    ->where('active', true)
                                    ->where(
                                        'code',
                                        'XPERTA'
                                    )
                        )
            )
            ->orderBy('priority')
            ->orderBy('id')
            ->first();
    }
}
