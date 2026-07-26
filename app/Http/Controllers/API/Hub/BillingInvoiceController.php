<?php

namespace App\Http\Controllers\API\Hub;

use App\Exceptions\ApiHub\Billing\BillingApiException;
use App\Http\Controllers\Controller;
use App\Services\ApiHub\Billing\ApiBillingDocumentDownloadService;
use App\Services\ApiHub\Billing\ApiBillingRequestService;
use App\Services\ApiHub\Billing\BillingRequestValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class BillingInvoiceController extends Controller
{
    public function __construct(
        private readonly BillingRequestValidator $validator,
        private readonly ApiBillingRequestService $service
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $apiClient = $request->attributes->get(
                'api_client'
            );
            $apiKey = $request->attributes->get(
                'api_key'
            );

            $payload = $this->validator->validate(
                $request
            );

            $result = $this->service->createOrReplay(
                $apiClient,
                $apiKey,
                $payload
            );

            $replayed = (bool) $result['replayed'];

            return response()->json([
                'success' => true,
                'message' => $replayed
                    ? 'La solicitud ya existía y fue recuperada mediante idempotencia.'
                    : 'La solicitud de facturación fue recibida correctamente.',
                'data' => $this->service->present(
                    $result['request'],
                    $replayed
                ),
                'meta' => [
                    'product' => 'BILLING',
                    'provider_mode' => 'MANUAL',
                    'next_action' => $replayed
                        ? 'CONSULT_STATUS'
                        : 'MANUAL_FULFILLMENT',
                ],
            ], $replayed ? 200 : 202);
        } catch (BillingApiException $exception) {
            return $this->errorResponse($exception);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'BILLING_INTERNAL_ERROR',
                    'message' =>
                        'No fue posible registrar la solicitud de facturación.',
                ],
            ], 500);
        }
    }

    public function show(
        Request $request,
        string $externalId
    ): JsonResponse {
        try {
            $billingRequest = $this->service->findForClient(
                $request->attributes->get('api_client'),
                $request->attributes->get('api_key'),
                $externalId
            );

            return response()->json([
                'success' => true,
                'data' => $this->service->present(
                    $billingRequest
                ),
                'meta' => [
                    'product' => 'BILLING',
                ],
            ]);
        } catch (BillingApiException $exception) {
            return $this->errorResponse($exception);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'BILLING_INTERNAL_ERROR',
                    'message' =>
                        'No fue posible consultar la solicitud de facturación.',
                ],
            ], 500);
        }
    }

    public function document(
        Request $request,
        string $externalId,
        string $format,
        ApiBillingDocumentDownloadService $documents
    ): Response|JsonResponse {
        try {
            $billingRequest = $this->service->findForClient(
                $request->attributes->get('api_client'),
                $request->attributes->get('api_key'),
                $externalId
            );

            return $documents->download(
                $billingRequest,
                $format
            );
        } catch (BillingApiException $exception) {
            return $this->errorResponse($exception);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'BILLING_DOCUMENT_ERROR',
                    'message' =>
                        'No fue posible descargar el documento fiscal.',
                ],
            ], 500);
        }
    }

    private function errorResponse(
        BillingApiException $exception
    ): JsonResponse {
        $error = [
            'code' => $exception->errorCode(),
            'message' => $exception->getMessage(),
        ];

        if ($exception->details() !== []) {
            $error['details'] = $exception->details();
        }

        return response()->json([
            'success' => false,
            'error' => $error,
        ], $exception->statusCode());
    }
}
