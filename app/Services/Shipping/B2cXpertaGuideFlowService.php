<?php

namespace App\Services\Shipping;

use App\Models\B2cCotizacion;
use App\Services\Payments\PaymentVerificationService;
use App\Services\Shipping\Xperta\XpertaGuideService;
use App\Services\Shipping\Xperta\XpertaGuideResponseNormalizer;
use App\Services\Shipping\Xperta\XpertaProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class B2cXpertaGuideFlowService
{
    private array $lastResponseSnapshot = [];

    public function __construct(
        private XpertaGuideService $provider,
        private PaymentVerificationService $payments,
        private XpertaGuideResponseNormalizer $responseNormalizer
    ) {
    }

    public function generate(B2cCotizacion $cotizacion, ?int $userId): B2cCotizacion
    {
        return $this->generateAuthorized($cotizacion, $userId, false);
    }

    public function generateAfterConfirmedPayment(
        B2cCotizacion $cotizacion
    ): B2cCotizacion {
        Log::info('Inicio de generación automática de guía Xperta', $this->diagnosticContext($cotizacion) + [
            'source' => 'confirmed_payment',
        ]);
        return $this->generateAuthorized($cotizacion, null, true);
    }

    private function generateAuthorized(
        B2cCotizacion $cotizacion,
        ?int $userId,
        bool $systemTrigger
    ): B2cCotizacion {
        $this->assertOperationalEnabled($cotizacion);

        $claimed = DB::transaction(function () use ($cotizacion, $userId, $systemTrigger) {
            $locked = B2cCotizacion::query()->whereKey($cotizacion->id)->lockForUpdate()->firstOrFail();

            if (!$systemTrigger && (int) $locked->user_id !== $userId) {
                $this->reject($locked, 'OWNERSHIP_REJECTED', 'La cotización no pertenece al usuario autenticado.');
                abort(403);
            }
            if ($locked->hasGeneratedGuide()) {
                Log::info('Generación de guía Xperta omitida', $this->diagnosticContext($locked) + ['reason_code' => 'GUIDE_ALREADY_EXISTS']);
                return $locked;
            }
            if ($locked->quote_expires_at && $locked->quote_expires_at->isPast()) {
                $this->reject($locked, 'QUOTE_EXPIRED', 'La cotización venció. Vuelve a cotizar antes de generar la guía.');
            }
            if (!in_array((string) $locked->payment_status, ['approved', 'saldo_prepago'], true)) {
                $this->reject($locked, 'PAYMENT_NOT_APPROVED', 'El pago todavía no está aprobado.');
            }
            if (!$locked->hasAccreditedPayment() || !$this->payments->isEligibleForGuide($locked)) {
                $this->reject($locked, 'PAYMENT_NOT_VERIFIED', 'El pago todavía no está verificado.');
            }
            if (!in_array(strtolower(trim((string) $locked->service_code)), ['terrestre', 'diasig'], true)) {
                $this->reject($locked, 'SERVICE_NOT_SUPPORTED', 'El servicio seleccionado no permite generar una guía Xperta.');
            }
            if (!$locked->hasCompleteShippingAddresses() || !$locked->hasCompletePackageData()) {
                $this->reject($locked, 'MISSING_REQUIRED_DATA', 'Completa los datos del remitente, destinatario y paquete.');
            }
            if (!preg_match('/^\d{5}$/', (string) $locked->cp_origen)
                || !preg_match('/^\d{5}$/', (string) $locked->cp_destino)) {
                $this->reject($locked, 'MISSING_REQUIRED_DATA', 'Los códigos postales deben tener cinco dígitos.');
            }
            if ((float) ($locked->peso_facturable ?: $locked->peso) <= 0) {
                $this->reject($locked, 'MISSING_REQUIRED_DATA', 'El peso facturable debe ser mayor que cero.');
            }
            if ((float) $locked->zigo_final_price <= 0
                || (float) $locked->precio <= 0
                || trim((string) $locked->quote_request_fingerprint) === '') {
                $this->reject($locked, 'MISSING_REQUIRED_DATA', 'El precio congelado de la cotización no es válido.');
            }
            if (strtolower((string) $locked->carrier) !== 'estafeta' || strtolower((string) $locked->provider) !== 'xperta') {
                $this->reject($locked, 'SERVICE_NOT_SUPPORTED', 'El servicio seleccionado no corresponde a Xperta/Estafeta.');
            }

            if (strtoupper((string) $locked->guia_estatus) === 'GENERANDO'
                && $locked->guia_generation_started_at
                && $locked->guia_generation_started_at->gt(now()->subMinutes(5))) {
                $this->reject($locked, 'GUIDE_ALREADY_EXISTS', 'La guía ya se está generando. Espera antes de reintentar.');
            }
            if ((int) $locked->guia_generation_attempts >= (int) config('zigo_b2c_xperta.guide_max_attempts', 3)) {
                $this->reject($locked, 'MAX_ATTEMPTS_REACHED', 'La guía alcanzó el máximo de intentos permitidos.');
            }
            if (in_array((string) $locked->guia_last_error_code, ['GUIDE_RESPONSE_INCOMPLETE', 'GUIDE_RESPONSE_REQUIRES_REVIEW', 'XPERTA_TIMEOUT'], true)) {
                throw new RuntimeException('El intento anterior tuvo un resultado ambiguo y requiere conciliación operativa antes de reintentar.');
            }

            $fingerprint = hash('sha256', implode('|', [
                $locked->id, $locked->quote_request_fingerprint, $locked->service_code,
                $locked->payment_verified_external_reference ?: $locked->payment_external_reference,
            ]));

            $locked->forceFill([
                'guia_provider_reference' => $locked->guia_provider_reference ?: 'ZIGO-B2C-XP-' . $locked->id,
                'guia_request_fingerprint' => $fingerprint,
                'guia_generation_attempts' => ((int) $locked->guia_generation_attempts) + 1,
                'guia_generation_started_at' => now(),
                'guia_last_attempt_at' => now(),
                'guia_estatus' => 'GENERANDO',
                'guia_request_snapshot' => $this->provider->buildPayload($locked, false),
                'guia_last_error_code' => null,
                'guia_last_error_message' => null,
            ])->save();

            return $locked->refresh();
        });

        if ($claimed->hasGeneratedGuide()) {
            return $claimed;
        }

        try {
            $result = $this->provider->createWithMeta($claimed, (string) ($claimed->service_code ?: $claimed->servicio));
            return $this->persistResponse($claimed, $result, $userId);
        } catch (Throwable $exception) {
            Log::error('Llamada al proveedor Xperta fallida', $this->diagnosticContext($claimed) + [
                'reason_code' => 'PROVIDER_CALL_FAILED',
                'exception' => get_class($exception),
            ]);
            $this->markFailure($claimed, $exception);
            throw new RuntimeException($this->safeMessage($exception), 0, $exception);
        }
    }

    private function persistResponse(B2cCotizacion $cotizacion, array $result, ?int $userId): B2cCotizacion
    {
        $data = (array) ($result['data'] ?? []);
        $this->lastResponseSnapshot = $this->sanitizeSnapshot($result);
        $normalized = $this->responseNormalizer->normalize($result);
        $tracking = $normalized['tracking'] ?: $this->firstString($data, [
            'trackingNumber', 'tracking_number', 'trackingNo', 'tracking',
            'masterTrackingNumber', 'waybill', 'wayBill', 'guideNumber', 'numeroGuia', 'guia',
            'data.trackingNumber', 'data.tracking_number', 'data.waybill', 'data.guia', 'data.numeroGuia',
            'output.transactionShipments.0.masterTrackingNumber',
            'data.output.transactionShipments.0.masterTrackingNumber',
        ]);
        $shipmentId = $normalized['waybill'] ?: $this->firstString($data, [
            'shipmentId', 'shipment_id', 'shipment', 'reference', 'id', 'data.shipmentId',
            'providerShipmentId', 'data.providerShipmentId', 'data.shipment', 'data.reference',
        ]);
        $requestNumber = $normalized['provider_request_number'] ?: $this->firstString($data, [
            'requestNumber', 'request_number', 'data.requestNumber',
        ]);
        [$labelPath, $labelFormat] = in_array($normalized['document_type'], ['url', 'omitted'], true)
            ? [null, null]
            : $this->storeLabel($cotizacion, $data);
        if ($tracking === null && $labelPath === null) {
            throw new RuntimeException('GUIDE_RESPONSE_INCOMPLETE: Xperta no devolvió guía ni documento válido.');
        }

        return DB::transaction(function () use ($cotizacion, $result, $normalized, $tracking, $shipmentId, $requestNumber, $labelPath, $labelFormat, $userId) {
            $locked = B2cCotizacion::query()->whereKey($cotizacion->id)->lockForUpdate()->firstOrFail();
            if ($locked->hasGeneratedGuide()) {
                return $locked;
            }
            $locked->forceFill([
                'guia_id' => $normalized['waybill'],
                'tracking_number' => $tracking,
                'guia_provider_shipment_id' => $shipmentId,
                'guia_provider_reference' => $normalized['provider_reference'] ?: $locked->guia_provider_reference,
                'guia_provider_request_number' => ctype_digit((string) $requestNumber)
                    ? (int) $requestNumber
                    : null,
                'documento' => $labelPath,
                'guia_label_format' => $labelFormat,
                'guia_estatus' => 'GENERADA',
                'estatus' => 'GUIA_GENERADA',
                'guia_provider_status' => $normalized['provider_status'] === 'GENERADA' ? 'GENERADA' : 'CREATED',
                'guia_generated_by' => $userId,
                'guia_generated_at' => now(),
                'guia_generation_started_at' => null,
                'quote_correlation_id' => $this->sanitizeCorrelation($result['correlation_id'] ?? null),
                'guia_response_snapshot' => $this->sanitizeSnapshot($result),
            ])->save();
            return $locked->refresh();
        });
    }

    private function storeLabel(B2cCotizacion $cotizacion, array $data): array
    {
        $encoded = $this->firstString($data, [
            'data.data', 'document', 'document.content', 'document.base64', 'documento', 'pdf', 'pdfBase64', 'documentBase64',
            'label', 'labelContent', 'label.content',
            'data.document', 'data.document.content', 'data.document.base64', 'data.documento', 'data.pdf', 'data.pdfBase64', 'data.documentBase64',
            'data.label', 'data.labelContent', 'data.label.content',
        ]);
        $labelUrl = $this->firstString($data, [
            'documentUrl', 'documentURL', 'pdfUrl', 'labelUrl', 'labelURL', 'url', 'label.url',
            'data.documentUrl', 'data.documentURL', 'data.pdfUrl', 'data.labelUrl', 'data.labelURL', 'data.url', 'data.label.url',
            'output.transactionShipments.0.pieceResponses.0.packageDocuments.0.url',
            'data.output.transactionShipments.0.pieceResponses.0.packageDocuments.0.url',
        ]);
        if ($encoded !== null && filter_var($encoded, FILTER_VALIDATE_URL)) {
            $labelUrl = $encoded;
            $encoded = null;
        }
        if ($labelUrl !== null) {
            throw new RuntimeException('GUIDE_RESPONSE_REQUIRES_REVIEW: Xperta devolvió una URL de documento.');
        }
        if ($encoded !== null) {
            $encoded = preg_replace('#^data:application/pdf;base64,#i', '', $encoded);
            $binary = base64_decode($encoded, true);
        } else {
            return [null, null];
        }
        if ($binary === false || !str_starts_with($binary, '%PDF-')) {
            return [null, null];
        }
        $path = 'private/b2c/labels/' . $cotizacion->id . '/' . Str::uuid() . '.pdf';
        if (!Storage::disk('local')->put($path, $binary)) {
            throw new RuntimeException('La etiqueta fue recibida pero no pudo guardarse.');
        }
        return [$path, 'PDF'];
    }

    private function markFailure(B2cCotizacion $cotizacion, Throwable $exception): void
    {
        $code = str_starts_with($exception->getMessage(), 'GUIDE_RESPONSE_INCOMPLETE')
            ? 'GUIDE_RESPONSE_INCOMPLETE'
            : (str_starts_with($exception->getMessage(), 'GUIDE_RESPONSE_REQUIRES_REVIEW')
            ? 'GUIDE_RESPONSE_REQUIRES_REVIEW'
            : ($exception instanceof XpertaProviderException
            ? $exception->errorCode
            : ($exception instanceof ConnectionException ? 'XPERTA_TIMEOUT' : 'XPERTA_PROVIDER_ERROR')));
        Log::warning('Fallo funcional al generar guía B2C Xperta', [
            'cotizacion_id' => $cotizacion->id, 'error_code' => $code,
            'exception' => get_class($exception),
        ]);
        $diagnostic = $this->failureDiagnostic($exception, $code);
        $cotizacion->refresh()->forceFill([
            'guia_estatus' => 'ERROR_PROVEEDOR', 'estatus' => 'ERROR_GENERACION_GUIA',
            'guia_generation_started_at' => null, 'guia_last_error_code' => $code,
            'guia_last_error_message' => $this->safeMessage($exception),
            'guia_response_snapshot' => $this->lastResponseSnapshot !== []
                ? $this->lastResponseSnapshot
                : $diagnostic,
        ])->save();
    }

    private function failureDiagnostic(Throwable $exception, string $code): array
    {
        $result = ['provider' => 'xperta', 'success' => false, 'error_code' => $code];
        if (!$exception instanceof XpertaProviderException) return $result;
        foreach (['http_status', 'provider_code', 'provider_message', 'correlation_id', 'request_id'] as $key) {
            if (!array_key_exists($key, $exception->diagnosticMetadata) || !is_scalar($exception->diagnosticMetadata[$key])) continue;
            $value = preg_replace('/(token|api.?key|password|secret)\s*[:=]\s*[^\s,;]+/i', '$1=[REDACTED]', (string) $exception->diagnosticMetadata[$key]);
            $result[$key] = mb_substr($value, 0, $key === 'provider_message' ? 500 : 100);
        }
        if (!isset($result['provider_code']) && isset($exception->diagnosticMetadata['provider_message_code'])) {
            $result['provider_code'] = mb_substr((string) $exception->diagnosticMetadata['provider_message_code'], 0, 100);
        }
        return $result;
    }

    private function safeMessage(Throwable $exception): string
    {
        $code = $exception instanceof XpertaProviderException ? $exception->errorCode : '';
        if ($exception instanceof XpertaProviderException
            && isset($exception->diagnosticMetadata['provider_message'])
            && is_string($exception->diagnosticMetadata['provider_message'])) {
            return mb_substr(preg_replace(
                '/(token|api.?key|password|secret)\s*[:=]\s*[^\s,;]+/i',
                '$1=[REDACTED]',
                $exception->diagnosticMetadata['provider_message']
            ), 0, 500);
        }
        return match ($code) {
            'XPERTA_CORPORATE_UNAUTHORIZED' => 'El corporativo no está autorizado para generar esta guía.',
            'XPERTA_API_KEY_UNAUTHORIZED', 'XPERTA_CREDENTIALS_UNAUTHORIZED' => 'Las credenciales del proveedor no fueron aceptadas.',
            default => $exception instanceof ConnectionException
                ? 'Xperta no respondió a tiempo. El pago permanece registrado y puedes reintentar.'
                : (str_starts_with($exception->getMessage(), 'GUIDE_RESPONSE_INCOMPLETE')
                    ? 'Xperta devolvió una guía incompleta. Puedes reintentar sin generar un duplicado.'
                    : (str_starts_with($exception->getMessage(), 'GUIDE_RESPONSE_REQUIRES_REVIEW')
                    ? 'Xperta devolvió una URL de documento cuyo tratamiento requiere revisión.'
                    : 'No fue posible generar la guía. El pago permanece registrado y puedes reintentar.')),
        };
    }

    private function firstString(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = data_get($data, $key);
            if (is_string($value) && trim($value) !== '') return trim($value);
            if (is_numeric($value)) return (string) $value;
        }
        return null;
    }

    private function sanitizeCorrelation(mixed $value): ?string
    {
        $value = preg_replace('/[^A-Za-z0-9._:-]/', '', (string) $value);
        return $value === '' ? null : mb_substr($value, 0, 100);
    }

    private function sanitizeSnapshot(array $value): array
    {
        $walk = function (mixed $item, ?string $key = null) use (&$walk): mixed {
            if ($key !== null && preg_match('/token|api.?key|password|secret/i', $key)) {
                return '[REDACTED]';
            }
            if ($key !== null && preg_match('/label(content)?|base64/i', $key)
                && is_string($item)
                && !filter_var($item, FILTER_VALIDATE_URL)) {
                return '[CONTENT_OMITTED]';
            }
            if (is_array($item)) {
                $clean = [];
                foreach ($item as $childKey => $child) {
                    $clean[$childKey] = $walk($child, (string) $childKey);
                }
                return $clean;
            }
            if (is_string($item) && strlen($item) > 4000) {
                return '[CONTENT_OMITTED]';
            }
            return $item;
        };

        return $walk($value);
    }

    private function assertOperationalEnabled(B2cCotizacion $cotizacion): void
    {
        if (!config('services.xperta.enabled') || !config('services.xperta.guide_enabled')) {
            $this->reject($cotizacion, 'GUIDE_FLAG_DISABLED', 'La generación de guía Xperta no está habilitada.');
        }
        if (!config('zigo_b2c_xperta.guide_enabled')) {
            $this->reject($cotizacion, 'B2C_GUIDE_FLAG_DISABLED', 'La generación B2C de guía Xperta no está habilitada.');
        }
        if (!in_array(strtolower((string) config('services.xperta.environment')), ['production', 'stage', 'staging'], true)) {
            $this->reject($cotizacion, 'INVALID_ENVIRONMENT', 'El ambiente no permite generar guías Xperta.');
        }
    }

    private function reject(B2cCotizacion $cotizacion, string $reasonCode, string $message): never
    {
        Log::warning('Generación de guía Xperta rechazada', $this->diagnosticContext($cotizacion) + ['reason_code' => $reasonCode]);
        throw new RuntimeException($reasonCode . ': ' . $message);
    }

    private function diagnosticContext(B2cCotizacion $cotizacion): array
    {
        return [
            'cotizacion_id' => $cotizacion->id,
            'app_environment' => app()->environment(),
            'xperta_environment' => config('services.xperta.environment'),
            'payment_status' => $cotizacion->payment_status,
            'payment_verified_at_present' => $cotizacion->payment_verified_at !== null,
            'estatus' => $cotizacion->estatus,
            'service_code' => $cotizacion->service_code,
            'guide_enabled' => (bool) config('services.xperta.guide_enabled'),
            'b2c_guide_enabled' => (bool) config('zigo_b2c_xperta.guide_enabled'),
            'attempts' => (int) $cotizacion->guia_generation_attempts,
        ];
    }
}
