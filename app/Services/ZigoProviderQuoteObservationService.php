<?php

namespace App\Services;

use App\Models\B2cCotizacion;
use App\Models\ZigoAgreementService;
use App\Models\ZigoProviderQuoteObservation;
use Illuminate\Support\Facades\Log;
use Throwable;

class ZigoProviderQuoteObservationService
{
    public function selectionMetadata(B2cCotizacion $cotizacion): array
    {
        try {
            $observation = ZigoProviderQuoteObservation::query()
                ->where('b2c_cotizacion_id', $cotizacion->id)
                ->where('service_code', $cotizacion->service_code)
                ->where('success', true)
                ->latest('id')
                ->first();

            return (array) data_get(
                $observation?->response_payload,
                '_zigo_selection',
                []
            );
        } catch (Throwable $exception) {
            Log::warning(
                'ZIGO Xperta - No se pudo leer metadata de selección',
                [
                    'cotizacion_id' => $cotizacion->id,
                    'exception' => get_class($exception),
                ]
            );

            return [];
        }
    }

    public function attachSelectionMetadata(
        B2cCotizacion $cotizacion,
        array $option
    ): void {
        try {
            $observation = ZigoProviderQuoteObservation::query()
                ->where('b2c_cotizacion_id', $cotizacion->id)
                ->where('service_code', $option['service_code'])
                ->where('success', true)
                ->latest('id')
                ->first();

            if (!$observation) {
                return;
            }

            $payload = (array) $observation->response_payload;
            $payload['_zigo_selection'] = [
                'estimated_delivery_date' => $option['estimated_delivery_date'],
                'zone_code' => $option['zone_code'],
                'periodicity_name' => $option['periodicity_name'],
                'operating_days' => $option['operating_days'],
                'is_reexpedition' => $option['is_reexpedition'],
                'is_ocurre' => $option['is_ocurre'],
                'restriction' => $option['restriction'],
                'restriction_description' => $option['restriction_description'],
                'commercial_breakdown' => $option['commercial_breakdown'] ?? [],
                'commercial_subtotal' => $option['commercial_subtotal'] ?? null,
                'commercial_vat' => $option['commercial_vat'] ?? null,
                'commercial_total' => $option['commercial_price'] ?? null,
            ];
            $payload['_zigo_internal_pricing'] = data_get(
                $option,
                'commercial_quote.concept_pricing'
            );

            $observation->update(['response_payload' => $payload]);
        } catch (Throwable $exception) {
            Log::warning(
                'ZIGO Xperta - No se pudo adjuntar metadata de selección',
                [
                    'cotizacion_id' => $cotizacion->id,
                    'service_code' => $option['service_code'] ?? null,
                    'exception' => get_class($exception),
                ]
            );
        }
    }

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
            providerData: $providerData
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
            errorMessage: $exception->getMessage(),
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
                    'message' => $exception->getMessage(),
                ]
            );

            return null;
        }
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
