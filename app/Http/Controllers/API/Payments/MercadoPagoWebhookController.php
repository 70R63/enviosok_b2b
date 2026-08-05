<?php

namespace App\Http\Controllers\API\Payments;

use App\Http\Controllers\Controller;
use App\Models\B2cCotizacion;
use App\Services\Payments\MercadoPagoPaymentClient;
use App\Services\Payments\MercadoPagoWebhookSignatureValidator;
use App\Services\Payments\PaymentVerificationService;
use App\Services\Shipping\B2cXpertaGuideFlowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MercadoPagoWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        MercadoPagoWebhookSignatureValidator $signatureValidator,
        MercadoPagoPaymentClient $paymentClient,
        PaymentVerificationService $verificationService,
        B2cXpertaGuideFlowService $xpertaGuideFlow
    ): JsonResponse {
        $paymentId = trim((string) (
            $request->query('data.id')
            ?: data_get($request->all(), 'data.id')
        ));

        if ($paymentId === '') {
            return response()->json([
                'received' => false,
                'message' => 'Falta el identificador del pago.',
            ], 400);
        }

        if (!$signatureValidator->isValid($request, $paymentId)) {
            Log::warning('Webhook Mercado Pago con firma inválida', [
                'payment_id' => $paymentId,
                'request_id' => $request->header('x-request-id'),
            ]);

            return response()->json([
                'received' => false,
                'message' => 'Firma inválida.',
            ], 401);
        }

        try {
            $payload = $paymentClient->find($paymentId);
            $externalReference = trim((string) (
                $payload['external_reference'] ?? ''
            ));

            if (!preg_match('/^B2C-([0-9]+)$/', $externalReference, $matches)) {
                return response()->json([
                    'received' => true,
                    'processed' => false,
                    'reason' => 'Referencia ajena al flujo B2C.',
                ]);
            }

            $cotizacion = B2cCotizacion::query()
                ->find((int) $matches[1]);

            if (!$cotizacion) {
                return response()->json([
                    'received' => true,
                    'processed' => false,
                    'reason' => 'Cotización no encontrada.',
                ]);
            }

            $verified = $verificationService->verifyPayload(
                $cotizacion,
                $payload,
                'WEBHOOK'
            );

            if ($this->shouldGenerateXpertaGuide($verified)) {
                try {
                    $verified = $xpertaGuideFlow
                        ->generateAfterConfirmedPayment($verified);
                } catch (\Throwable $exception) {
                    $this->recordAutomaticGuideFailure($verified, $exception);
                    Log::warning('Pago confirmado; guía Xperta pendiente de recuperación', [
                        'cotizacion_id' => $verified->id,
                        'source' => 'WEBHOOK',
                        'guide_error_code' => $verified->fresh()->guia_last_error_code,
                        'exception' => get_class($exception),
                    ]);
                }
            }

            return response()->json([
                'received' => true,
                'processed' => true,
                'cotizacion_id' => $verified->id,
                'status' => $verified->estatus,
            ]);
        } catch (\Throwable $exception) {
            Log::error('Error procesando webhook Mercado Pago', [
                'payment_id' => $paymentId,
                'exception' => get_class($exception),
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'received' => false,
                'message' => 'No fue posible procesar la notificación.',
            ], 500);
        }
    }

    private function shouldGenerateXpertaGuide(
        B2cCotizacion $cotizacion
    ): bool {
        return $cotizacion->payment_status === 'approved'
            && $cotizacion->payment_verified_at !== null
            && !$cotizacion->hasGeneratedGuide()
            && strtolower((string) $cotizacion->provider) === 'xperta'
            && strtolower((string) $cotizacion->carrier) === 'estafeta';
    }

    private function recordAutomaticGuideFailure(B2cCotizacion $cotizacion, \Throwable $exception): void
    {
        $message = preg_replace('/token|api.?key|password|secret/i', '[REDACTED]', $exception->getMessage());
        $code = preg_match('/^([A-Z][A-Z0-9_]+):/', $exception->getMessage(), $matches)
            ? $matches[1]
            : 'PROVIDER_CALL_FAILED';
        $fresh = $cotizacion->fresh();
        if ($fresh && !$fresh->hasGeneratedGuide()) {
            $fresh->forceFill([
                'guia_last_error_code' => $fresh->guia_last_error_code ?: $code,
                'guia_last_error_message' => mb_substr((string) $message, 0, 500),
            ])->save();
        }
    }
}
