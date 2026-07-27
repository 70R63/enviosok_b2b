<?php

namespace App\Services\Shipping;

use App\Models\B2cCotizacion;
use App\Models\Guia;
use App\Negocio\Guias\EstafetaCreacion;
use App\Services\Payments\PaymentVerificationService;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class EstafetaGuideService
{
    public function __construct(
        private PaymentVerificationService $paymentVerificationService
    ) {
    }

    public function generate(
        B2cCotizacion $cotizacion,
        array $payload
    ): B2cCotizacion {
        $recovered = $this->recoverExistingGuide($cotizacion);

        if ($recovered) {
            return $recovered;
        }

        if (!$this->paymentVerificationService->isEligibleForGuide($cotizacion)) {
            throw new RuntimeException(
                'El pago todavía no cuenta con verificación suficiente '
                . 'para generar la guía.'
            );
        }

        if (
            !in_array(
                strtoupper((string) $cotizacion->estatus),
                ['PAGADA', 'ERROR_GENERACION_GUIA'],
                true
            )
        ) {
            throw new RuntimeException(
                'La cotización aún no está pagada.'
            );
        }

        $weight = (float) (
            $cotizacion->peso_facturable
            ?: $cotizacion->peso
        );

        if ($weight > 70.999) {
            $this->markValidationFailure(
                $cotizacion,
                'WEIGHT_LIMIT_EXCEEDED',
                'El peso facturable supera el máximo permitido '
                . 'por Estafeta (70.99 kg).'
            );

            throw new RuntimeException(
                'La guía no puede generarse porque el peso '
                . 'facturable de '
                . number_format($weight, 2)
                . ' kg supera el máximo permitido por Estafeta '
                . '(70.99 kg). El pago permanece registrado.'
            );
        }

        $cotizacion = $this->claimGeneration($cotizacion, $payload);

        if ($cotizacion->estatus === 'GUIA_GENERADA') {
            return $cotizacion;
        }

        $payload['numero_solicitud'] =
            $cotizacion->guia_provider_request_number;

        $generator = null;

        try {
            $generator = (
                new EstafetaCreacion()
            )->omitirCobroSaldoLegacy();

            $generator->parseoApi($payload);

            $guideId = $generator->getGuiaId();

            if (!$guideId) {
                throw new RuntimeException(
                    'El proveedor no devolvió el identificador '
                    . 'de la guía creada.'
                );
            }

            $guide = Guia::withoutGlobalScopes()->find($guideId);

            if (!$guide) {
                throw new RuntimeException(
                    'La guía fue procesada, pero no fue encontrada '
                    . 'en el sistema.'
                );
            }

            if (!$this->hasValidTracking($guide)) {
                throw new RuntimeException(
                    'Estafeta no devolvió un número de rastreo válido.'
                );
            }

            return $this->completeGeneration(
                $cotizacion,
                $guide,
                false
            );
        } catch (Throwable $exception) {
            $guideId = $generator?->getGuiaId();

            if ($guideId) {
                $partialGuide = Guia::withoutGlobalScopes()
                    ->find($guideId);

                if (
                    $partialGuide
                    && $this->hasValidTracking($partialGuide)
                ) {
                    return $this->completeGeneration(
                        $cotizacion,
                        $partialGuide,
                        true
                    );
                }
            }

            $recovered = $this->recoverExistingGuide($cotizacion);

            if ($recovered) {
                return $recovered;
            }

            $errorCode = $this->classifyError($exception);
            $safeMessage = $this->safeUserMessage($errorCode);

            $this->markProviderFailure(
                $cotizacion,
                $errorCode,
                $exception
            );

            throw new RuntimeException(
                $safeMessage,
                0,
                $exception
            );
        }
    }

    private function claimGeneration(
        B2cCotizacion $cotizacion,
        array $payload
    ): B2cCotizacion {
        return DB::transaction(function () use ($cotizacion, $payload) {
            $locked = B2cCotizacion::query()
                ->whereKey($cotizacion->id)
                ->lockForUpdate()
                ->firstOrFail();

            $recovered = $this->recoverExistingGuide($locked);

            if ($recovered) {
                return $recovered;
            }

            $staleMinutes = max(
                1,
                (int) config(
                    'services.estafeta.guide_generation_stale_minutes',
                    5
                )
            );

            if (
                strtoupper((string) $locked->guia_estatus) === 'GENERANDO'
                && $locked->guia_generation_started_at
                && $locked->guia_generation_started_at->gt(
                    now()->subMinutes($staleMinutes)
                )
            ) {
                throw new RuntimeException(
                    'La guía ya se está generando. Espera unos minutos '
                    . 'antes de volver a intentarlo.'
                );
            }

            $providerReference = trim(
                (string) $locked->guia_provider_reference
            );

            if ($providerReference === '') {
                $providerReference = 'ZIGO-B2C-' . $locked->id;
            }

            $providerRequestNumber =
                (int) $locked->guia_provider_request_number;

            if ($providerRequestNumber <= 0) {
                $providerRequestNumber =
                    2000000000 + (int) $locked->id;
            }

            $locked->update([
                'guia_provider_reference' => $providerReference,
                'guia_provider_request_number' =>
                    $providerRequestNumber,
                'guia_generation_attempts' =>
                    ((int) $locked->guia_generation_attempts) + 1,
                'guia_generation_started_at' => now(),
                'guia_last_attempt_at' => now(),
                'guia_last_error_code' => null,
                'guia_last_error_message' => null,
                'guia_request_snapshot' =>
                    $this->sanitizeRequest($locked, $payload),
                'guia_response_snapshot' => null,
                'guia_estatus' => 'GENERANDO',
            ]);

            return $locked->refresh();
        });
    }

    private function recoverExistingGuide(
        B2cCotizacion $cotizacion
    ): ?B2cCotizacion {
        $guide = null;

        if ($cotizacion->guia_id) {
            $guide = Guia::withoutGlobalScopes()
                ->find($cotizacion->guia_id);
        }

        if (!$this->hasValidTracking($guide)) {
            $requestNumber =
                (int) $cotizacion->guia_provider_request_number;

            if ($requestNumber > 0) {
                $guide = Guia::withoutGlobalScopes()
                    ->where('numero_solicitud', $requestNumber)
                    ->latest('id')
                    ->get()
                    ->first(fn (Guia $candidate) =>
                        $this->hasValidTracking($candidate)
                    );
            }
        }

        if (!$this->hasValidTracking($guide)) {
            return null;
        }

        return $this->completeGeneration(
            $cotizacion,
            $guide,
            true
        );
    }

    private function completeGeneration(
        B2cCotizacion $cotizacion,
        Guia $guide,
        bool $recovered
    ): B2cCotizacion {
        return DB::transaction(function () use (
            $cotizacion,
            $guide,
            $recovered
        ) {
            $locked = B2cCotizacion::query()
                ->whereKey($cotizacion->id)
                ->lockForUpdate()
                ->firstOrFail();

            $hasDocument = trim((string) $guide->documento) !== '';

            $locked->update([
                'guia_id' => $guide->id,
                'tracking_number' => $guide->tracking_number,
                'documento' => $guide->documento,
                'guia_estatus' => $hasDocument
                    ? 'GENERADA'
                    : 'GENERADA_SIN_DOCUMENTO',
                'estatus' => 'GUIA_GENERADA',
                'guia_generation_started_at' => null,
                'guia_generated_at' =>
                    $locked->guia_generated_at ?: now(),
                'guia_recovered_at' => $recovered
                    ? now()
                    : $locked->guia_recovered_at,
                'guia_last_error_code' => null,
                'guia_last_error_message' => null,
                'guia_response_snapshot' => [
                    'provider' => 'ESTAFETA',
                    'guide_id' => $guide->id,
                    'tracking_number' =>
                        (string) $guide->tracking_number,
                    'document_available' => $hasDocument,
                    'recovered' => $recovered,
                ],
            ]);

            return $locked->refresh();
        });
    }

    private function markValidationFailure(
        B2cCotizacion $cotizacion,
        string $code,
        string $message
    ): void {
        $cotizacion->update([
            'guia_estatus' => 'ERROR_VALIDACION_PESO',
            'guia_last_attempt_at' => now(),
            'guia_last_error_code' => $code,
            'guia_last_error_message' => $message,
        ]);
    }

    private function markProviderFailure(
        B2cCotizacion $cotizacion,
        string $code,
        Throwable $exception
    ): void {
        Log::error('Error generando guía B2C', [
            'cotizacion_id' => $cotizacion->id,
            'provider_reference' =>
                $cotizacion->guia_provider_reference,
            'error_code' => $code,
            'exception' => get_class($exception),
            'error' => $exception->getMessage(),
        ]);

        $cotizacion->refresh()->update([
            'guia_estatus' => 'ERROR_PROVEEDOR',
            'estatus' => 'ERROR_GENERACION_GUIA',
            'guia_generation_started_at' => null,
            'guia_last_error_code' => $code,
            'guia_last_error_message' => mb_substr(
                $exception->getMessage(),
                0,
                2000
            ),
            'guia_response_snapshot' => [
                'provider' => 'ESTAFETA',
                'success' => false,
                'error_code' => $code,
                'exception' => get_class($exception),
            ],
        ]);
    }

    private function sanitizeRequest(
        B2cCotizacion $cotizacion,
        array $payload
    ): array {
        return [
            'provider' => 'ESTAFETA',
            'provider_reference' =>
                $cotizacion->guia_provider_reference
                ?: 'ZIGO-B2C-' . $cotizacion->id,
            'provider_request_number' =>
                (int) (
                    $cotizacion->guia_provider_request_number
                    ?: 2000000000 + (int) $cotizacion->id
                ),
            'service' => $cotizacion->servicio,
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
            'weight' => (float) (
                $cotizacion->peso_facturable
                ?: $cotizacion->peso
            ),
            'dimensions' => $cotizacion->medidas,
            'insured' =>
                (bool) $cotizacion->requiere_seguro_envio,
            'declared_value' =>
                (float) $cotizacion->valor_declarado,
            'format' => $payload['formatoImpresion'] ?? null,
        ];
    }

    private function hasValidTracking(?Guia $guide): bool
    {
        if (!$guide) {
            return false;
        }

        $tracking = trim((string) $guide->tracking_number);

        if ($tracking === '') {
            return false;
        }

        foreach (
            [
                'Exception',
                'SAXParseException',
                'ERROR',
                'FAULT',
            ] as $invalidMarker
        ) {
            if (stripos($tracking, $invalidMarker) !== false) {
                return false;
            }
        }

        return true;
    }

    private function classifyError(Throwable $exception): string
    {
        return match (true) {
            $exception instanceof ConnectException =>
                'PROVIDER_CONNECTION_ERROR',

            $exception instanceof RequestException =>
                'PROVIDER_HTTP_ERROR',

            $exception instanceof ValidationException =>
                'PROVIDER_VALIDATION_ERROR',

            default => 'GUIDE_GENERATION_ERROR',
        };
    }

    private function safeUserMessage(string $errorCode): string
    {
        return match ($errorCode) {
            'PROVIDER_CONNECTION_ERROR' =>
                'Estafeta no respondió temporalmente. El pago permanece '
                . 'registrado y puedes reintentar la guía.',

            'PROVIDER_HTTP_ERROR' =>
                'Estafeta rechazó temporalmente la solicitud. El pago '
                . 'permanece registrado y la guía puede reintentarse.',

            'PROVIDER_VALIDATION_ERROR' =>
                'Estafeta rechazó uno de los datos del envío. Revisa la '
                . 'información o reporta una incidencia.',

            default =>
                'No fue posible concluir la generación de la guía. '
                . 'El pago permanece registrado y puedes reintentar.',
        };
    }
}
