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
                } catch (\RuntimeException $exception) {
                    Log::warning('Pago confirmado; guía Xperta pendiente de recuperación', [
                        'cotizacion_id' => $verified->id,
                        'guide_error_code' => $verified->fresh()->guia_last_error_code,
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
        return $cotizacion->hasAccreditedPayment()
            && !$cotizacion->hasGeneratedGuide()
            && strtolower((string) $cotizacion->provider) === 'xperta'
            && strtolower((string) $cotizacion->carrier) === 'estafeta'
            && config('services.xperta.enabled', false)
            && config('services.xperta.guide_enabled', false)
            && config('zigo_b2c_xperta.guide_enabled', false)
            && strtolower((string) config('services.xperta.environment')) === 'production';
    }
}
