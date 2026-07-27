<?php

namespace App\Services\ApiHub\Billing\Internal;

use App\Models\ApiBillingRequest;
use App\Models\ApiClient;
use App\Models\ApiClientProduct;
use App\Models\ApiProduct;
use App\Models\B2cInvoiceRequest;
use App\Services\ApiHub\Billing\ApiBillingRequestService;
use App\Services\ApiHub\Billing\BillingRequestValidator;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class B2cInternalBillingSynchronizer
{
    public function __construct(
        private readonly B2cBillingSnapshotBuilder $builder,
        private readonly BillingRequestValidator $validator,
        private readonly ApiBillingRequestService $requestService
    ) {
    }

    public function preview(
        B2cInvoiceRequest $invoiceRequest,
        ?string $paymentFormOverride = null
    ): array {
        $payload = $this->builder->build(
            $invoiceRequest,
            $paymentFormOverride
        );
        $idempotencyKey = $this->builder->idempotencyKey(
            $invoiceRequest
        );

        return [
            'idempotency_key' => $idempotencyKey,
            'payload' => $this->validator->validatePayload(
                $payload,
                $idempotencyKey
            ),
        ];
    }

    public function sync(
        B2cInvoiceRequest $invoiceRequest,
        ?string $paymentFormOverride = null
    ): array {
        if (! (bool) config(
            'services.zigo_internal_billing.enabled',
            false
        )) {
            throw new RuntimeException(
                'La sincronización interna está desactivada. Configura ZIGO_INTERNAL_BILLING_ENABLED=true.'
            );
        }

        $invoiceRequest->refresh();

        if ($invoiceRequest->api_billing_request_id) {
            return [
                'request' => ApiBillingRequest::with('items')
                    ->findOrFail(
                        $invoiceRequest->api_billing_request_id
                    ),
                'replayed' => true,
                'linked' => true,
            ];
        }

        if (
            $invoiceRequest->status
            !== B2cInvoiceRequest::STATUS_SOLICITADA
        ) {
            throw new RuntimeException(
                'Solo las solicitudes B2C en estado SOLICITADA pueden sincronizarse de forma real.'
            );
        }

        $preview = $this->preview(
            $invoiceRequest,
            $paymentFormOverride
        );
        $apiClient = $this->resolveInternalClient();
        $environment = $this->environment();

        try {
            $result = $this->requestService
                ->createOrReplayInternal(
                    $apiClient,
                    $environment,
                    $preview['payload']
                );

            $apiBillingRequest = $result['request'];

            DB::transaction(function () use (
                $invoiceRequest,
                $apiBillingRequest
            ): void {
                $lockedRequest = B2cInvoiceRequest::query()
                    ->lockForUpdate()
                    ->findOrFail($invoiceRequest->getKey());

                if (
                    $lockedRequest->api_billing_request_id
                    && (int) $lockedRequest->api_billing_request_id
                        !== (int) $apiBillingRequest->getKey()
                ) {
                    throw new RuntimeException(
                        'La solicitud B2C ya está vinculada con otra solicitud API Hub.'
                    );
                }

                $lockedRequest->update([
                    'api_billing_request_id' =>
                        $apiBillingRequest->getKey(),
                    'api_hub_synced_at' => now(),
                    'api_hub_sync_error' => null,
                ]);
            });

            return [
                'request' => $apiBillingRequest->load('items'),
                'replayed' => (bool) $result['replayed'],
                'linked' => true,
            ];
        } catch (Throwable $exception) {
            B2cInvoiceRequest::query()
                ->whereKey($invoiceRequest->getKey())
                ->update([
                    'api_hub_sync_error' => mb_substr(
                        $exception->getMessage(),
                        0,
                        2000
                    ),
                ]);

            throw $exception;
        }
    }

    private function resolveInternalClient(): ApiClient
    {
        $apiClientId = (int) config(
            'services.zigo_internal_billing.api_client_id'
        );

        if ($apiClientId <= 0) {
            throw new RuntimeException(
                'Configura ZIGO_INTERNAL_BILLING_API_CLIENT_ID con el cliente API interno de ZIGO.'
            );
        }

        $apiClient = ApiClient::query()
            ->whereKey($apiClientId)
            ->where('active', true)
            ->first();

        if (! $apiClient) {
            throw new RuntimeException(
                'El cliente API interno de ZIGO no existe o está suspendido.'
            );
        }

        $billingProduct = ApiProduct::query()
            ->where('code', 'BILLING')
            ->where('active', true)
            ->first();

        if (! $billingProduct) {
            throw new RuntimeException(
                'El producto BILLING no está disponible en API Hub.'
            );
        }

        $hasBillingAccess = ApiClientProduct::query()
            ->where('api_client_id', $apiClient->getKey())
            ->where('api_product_id', $billingProduct->getKey())
            ->where('active', true)
            ->exists();

        if (! $hasBillingAccess) {
            throw new RuntimeException(
                'El cliente API interno de ZIGO no tiene habilitado el producto BILLING.'
            );
        }

        return $apiClient;
    }

    private function environment(): string
    {
        $environment = strtolower(trim((string) config(
            'services.zigo_internal_billing.environment',
            'sandbox'
        )));

        if (! in_array(
            $environment,
            ['sandbox', 'production'],
            true
        )) {
            throw new RuntimeException(
                'El ambiente interno debe ser sandbox o production.'
            );
        }

        return $environment;
    }
}
