<?php

namespace App\Http\Controllers\API\Payments;

use App\Http\Controllers\Controller;
use App\Models\B2cCotizacion;
use App\Services\Payments\MercadoPagoPaymentClient;
use App\Services\Payments\MercadoPagoWebhookSignatureValidator;
use App\Services\Payments\PaymentVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MercadoPagoWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        MercadoPagoWebhookSignatureValidator $signatureValidator,
        MercadoPagoPaymentClient $paymentClient,
        PaymentVerificationService $verificationService
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
}
