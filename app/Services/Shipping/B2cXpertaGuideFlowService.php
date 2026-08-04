<?php

namespace App\Services\Shipping;

use App\Models\B2cCotizacion;
use App\Services\Payments\PaymentVerificationService;
use App\Services\Shipping\Xperta\XpertaGuideService;
use App\Services\Shipping\Xperta\XpertaProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class B2cXpertaGuideFlowService
{
    public function __construct(
        private XpertaGuideService $provider,
        private PaymentVerificationService $payments
    ) {
    }

    public function generate(B2cCotizacion $cotizacion, int $userId): B2cCotizacion
    {
        $this->assertStageEnabled();

        $claimed = DB::transaction(function () use ($cotizacion, $userId) {
            $locked = B2cCotizacion::query()->whereKey($cotizacion->id)->lockForUpdate()->firstOrFail();

            if ((int) $locked->user_id !== $userId) {
                abort(403);
            }
            if ($locked->hasGeneratedGuide()) {
                return $locked;
            }
            if ($locked->quote_expires_at && $locked->quote_expires_at->isPast()) {
                throw new RuntimeException('La cotización venció. Vuelve a cotizar antes de generar la guía.');
            }
            if (!$this->payments->isEligibleForGuide($locked) || !$locked->hasAccreditedPayment()) {
                throw new RuntimeException('El pago todavía no está confirmado.');
            }
            if (!$locked->hasCompleteShippingAddresses() || !$locked->hasCompletePackageData()) {
                throw new RuntimeException('Completa los datos del remitente, destinatario y paquete.');
            }
            if (strtolower((string) $locked->carrier) !== 'estafeta' || strtolower((string) $locked->provider) !== 'xperta') {
                throw new RuntimeException('El servicio seleccionado no corresponde a Xperta/Estafeta.');
            }

            if (strtoupper((string) $locked->guia_estatus) === 'GENERANDO'
                && $locked->guia_generation_started_at
                && $locked->guia_generation_started_at->gt(now()->subMinutes(5))) {
                throw new RuntimeException('La guía ya se está generando. Espera antes de reintentar.');
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
            $this->markFailure($claimed, $exception);
            throw new RuntimeException($this->safeMessage($exception), 0, $exception);
        }
    }

    private function persistResponse(B2cCotizacion $cotizacion, array $result, int $userId): B2cCotizacion
    {
        $data = (array) ($result['data'] ?? []);
        $tracking = $this->firstString($data, ['trackingNumber', 'tracking_number', 'waybill', 'guia', 'data.trackingNumber', 'data.waybill']);
        $shipmentId = $this->firstString($data, ['shipmentId', 'shipment_id', 'id', 'data.shipmentId']);
        if ($tracking === null) {
            throw new RuntimeException('Xperta no devolvió un número de guía válido.');
        }

        [$labelPath, $labelFormat] = $this->storeLabel($cotizacion, $data);

        return DB::transaction(function () use ($cotizacion, $result, $tracking, $shipmentId, $labelPath, $labelFormat, $userId) {
            $locked = B2cCotizacion::query()->whereKey($cotizacion->id)->lockForUpdate()->firstOrFail();
            if ($locked->hasGeneratedGuide()) {
                return $locked;
            }
            $locked->forceFill([
                'tracking_number' => $tracking,
                'guia_provider_shipment_id' => $shipmentId,
                'documento' => $labelPath,
                'guia_label_format' => $labelFormat,
                'guia_estatus' => $labelPath ? 'GENERADA' : 'GENERADA_SIN_DOCUMENTO',
                'estatus' => 'GUIA_GENERADA',
                'guia_provider_status' => 'CREATED',
                'guia_generated_by' => $userId,
                'guia_generated_at' => now(),
                'guia_generation_started_at' => null,
                'quote_correlation_id' => $this->sanitizeCorrelation($result['correlation_id'] ?? null),
                'guia_response_snapshot' => [
                    'provider' => 'xperta', 'carrier' => 'estafeta',
                    'tracking_number' => $tracking, 'shipment_id' => $shipmentId,
                    'label_available' => $labelPath !== null,
                ],
            ])->save();
            return $locked->refresh();
        });
    }

    private function storeLabel(B2cCotizacion $cotizacion, array $data): array
    {
        $encoded = $this->firstString($data, ['label', 'labelContent', 'label.content', 'data.label', 'data.labelContent']);
        $labelUrl = $this->firstString($data, ['labelUrl', 'labelURL', 'url', 'label.url', 'data.labelUrl', 'data.url']);
        if ($encoded !== null && filter_var($encoded, FILTER_VALIDATE_URL)) {
            $labelUrl = $encoded;
            $encoded = null;
        }
        if ($encoded === null && $labelUrl !== null) {
            $this->assertAllowedLabelUrl($labelUrl);
            $response = Http::accept('application/pdf')
                ->connectTimeout(5)->timeout(20)->withOptions(['allow_redirects' => false])
                ->get($labelUrl);
            if (!$response->successful()) {
                throw new RuntimeException('La etiqueta no pudo recuperarse del proveedor.');
            }
            $binary = $response->body();
        } elseif ($encoded !== null) {
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

    private function assertAllowedLabelUrl(string $url): void
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $configured = array_filter(array_map('trim', (array) config('zigo_b2c_xperta.allowed_label_hosts', [])));
        $baseHost = strtolower((string) parse_url((string) config('services.xperta.base_url'), PHP_URL_HOST));
        $allowed = array_unique(array_filter(array_map('strtolower', [...$configured, $baseHost])));
        if ($scheme !== 'https' || $host === '' || !in_array($host, $allowed, true)) {
            throw new RuntimeException('Xperta devolvió una ubicación de etiqueta no autorizada.');
        }
    }

    private function markFailure(B2cCotizacion $cotizacion, Throwable $exception): void
    {
        $code = $exception instanceof XpertaProviderException
            ? $exception->errorCode
            : ($exception instanceof ConnectionException ? 'XPERTA_TIMEOUT' : 'XPERTA_PROVIDER_ERROR');
        Log::warning('Fallo funcional al generar guía B2C Xperta', [
            'cotizacion_id' => $cotizacion->id, 'error_code' => $code,
            'exception' => get_class($exception),
        ]);
        $cotizacion->refresh()->forceFill([
            'guia_estatus' => 'ERROR_PROVEEDOR', 'estatus' => 'ERROR_GENERACION_GUIA',
            'guia_generation_started_at' => null, 'guia_last_error_code' => $code,
            'guia_last_error_message' => $this->safeMessage($exception),
            'guia_response_snapshot' => ['provider' => 'xperta', 'success' => false, 'error_code' => $code],
        ])->save();
    }

    private function safeMessage(Throwable $exception): string
    {
        $code = $exception instanceof XpertaProviderException ? $exception->errorCode : '';
        return match ($code) {
            'XPERTA_CORPORATE_UNAUTHORIZED' => 'El corporativo no está autorizado para generar esta guía.',
            'XPERTA_API_KEY_UNAUTHORIZED', 'XPERTA_CREDENTIALS_UNAUTHORIZED' => 'Las credenciales del proveedor no fueron aceptadas.',
            default => $exception instanceof ConnectionException
                ? 'Xperta no respondió a tiempo. El pago permanece registrado y puedes reintentar.'
                : (str_starts_with($exception->getMessage(), 'Xperta no devolvió') ? $exception->getMessage()
                    : 'No fue posible generar la guía. El pago permanece registrado y puedes reintentar.'),
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

    private function assertStageEnabled(): void
    {
        if (!config('zigo_b2c_xperta.full_flow_enabled') || !config('zigo_b2c_xperta.guide_enabled')) {
            throw new RuntimeException('La generación de guía Xperta no está habilitada.');
        }
        if (strtolower((string) config('services.xperta.environment')) !== 'stage') {
            throw new RuntimeException('La generación Xperta B2C está bloqueada fuera de Stage.');
        }
    }
}
