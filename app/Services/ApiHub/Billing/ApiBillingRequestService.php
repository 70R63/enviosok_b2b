<?php

namespace App\Services\ApiHub\Billing;

use App\Exceptions\ApiHub\Billing\BillingApiException;
use App\Models\ApiBillingRequest;
use App\Models\ApiClient;
use App\Models\ApiKey;
use App\Services\ApiHub\Webhooks\BillingWebhookPublisher;
use Illuminate\Support\Facades\DB;

class ApiBillingRequestService
{
    public function __construct(
        private readonly BillingRequestValidator $validator,
        private readonly BillingWebhookPublisher $webhookPublisher
    ) {
    }

    public function createOrReplay(
        ApiClient $apiClient,
        ApiKey $apiKey,
        array $payload
    ): array {
        return $this->createOrReplayForContext(
            $apiClient,
            $apiKey,
            strtolower(trim((string) $apiKey->environment)),
            $payload
        );
    }

    public function createOrReplayInternal(
        ApiClient $apiClient,
        string $environment,
        array $payload
    ): array {
        $environment = strtolower(trim($environment));

        if (! in_array(
            $environment,
            ['sandbox', 'production'],
            true
        )) {
            throw new BillingApiException(
                'El ambiente interno de facturación no es válido.',
                'INVALID_INTERNAL_ENVIRONMENT',
                422
            );
        }

        return $this->createOrReplayForContext(
            $apiClient,
            null,
            $environment,
            $payload
        );
    }

    private function createOrReplayForContext(
        ApiClient $apiClient,
        ?ApiKey $apiKey,
        string $environment,
        array $payload
    ): array {
        $payloadHash = $this->validator->payloadHash(
            $payload
        );

        return DB::transaction(function () use (
            $apiClient,
            $apiKey,
            $payload,
            $environment,
            $payloadHash
        ) {
            ApiClient::query()
                ->whereKey($apiClient->id)
                ->lockForUpdate()
                ->firstOrFail();

            $idempotentRequest = ApiBillingRequest::query()
                ->where('api_client_id', $apiClient->id)
                ->where('environment', $environment)
                ->where(
                    'idempotency_key',
                    $payload['idempotency_key']
                )
                ->first();

            if ($idempotentRequest) {
                if (
                    hash_equals(
                        $idempotentRequest->payload_hash,
                        $payloadHash
                    )
                ) {
                    return [
                        'request' => $idempotentRequest
                            ->load('items'),
                        'replayed' => true,
                    ];
                }

                throw new BillingApiException(
                    'La llave de idempotencia ya fue utilizada con una solicitud diferente.',
                    'IDEMPOTENCY_CONFLICT',
                    409
                );
            }

            $externalRequest = ApiBillingRequest::query()
                ->where('api_client_id', $apiClient->id)
                ->where('environment', $environment)
                ->where('external_id', $payload['external_id'])
                ->first();

            if ($externalRequest) {
                throw new BillingApiException(
                    'El external_id ya existe para este cliente y ambiente.',
                    'EXTERNAL_ID_CONFLICT',
                    409,
                    [
                        'external_id' => [
                            $payload['external_id'],
                        ],
                        'current_status' => [
                            $externalRequest->status,
                        ],
                    ]
                );
            }

            $request = ApiBillingRequest::create([
                'api_client_id' => $apiClient->id,
                'api_key_id' => $apiKey?->id,
                'environment' => $environment,
                'external_id' => $payload['external_id'],
                'idempotency_key' =>
                    $payload['idempotency_key'],
                'payload_hash' => $payloadHash,
                'source_system' => $payload['source_system'],
                'status' => ApiBillingRequest::STATUS_SOLICITADA,
                'fulfillment_mode' => 'MANUAL',
                'payment_reference' =>
                    $payload['payment']['reference'],
                'payment_status' => $payload['payment']['status'],
                'payment_method' => $payload['payment']['method'],
                'payment_form' => $payload['payment']['form'],
                'payment_date' =>
                    $payload['payment']['date'] ?? null,
                'currency' => $payload['payment']['currency'],
                'exchange_rate' =>
                    $payload['payment']['exchange_rate'] ?? null,
                'subtotal' => $payload['amounts']['subtotal'],
                'discount_total' =>
                    $payload['amounts']['discount'],
                'tax_total' => $payload['amounts']['tax'],
                'shipping_total' =>
                    $payload['amounts']['shipping'],
                'insurance_total' =>
                    $payload['amounts']['insurance'],
                'total' => $payload['amounts']['total'],
                'customer_rfc' => $payload['customer']['rfc'],
                'customer_name' => $payload['customer']['name'],
                'customer_postal_code' =>
                    $payload['customer']['postal_code'],
                'customer_tax_regime' =>
                    $payload['customer']['tax_regime'],
                'customer_cfdi_use' =>
                    $payload['customer']['cfdi_use'],
                'customer_email' =>
                    $payload['customer']['email'] ?? null,
                'request_payload' => $payload,
                'requested_at' => now(),
            ]);

            foreach ($payload['items'] as $index => $item) {
                $request->items()->create([
                    'line_number' => $index + 1,
                    'client_item_id' =>
                        $item['client_item_id'] ?? null,
                    'category' => $item['category'],
                    'product_service_code' =>
                        $item['product_service_code'],
                    'unit_code' => $item['unit_code'],
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount' => $item['discount'],
                    'subtotal' => $item['subtotal'],
                    'tax_object' => $item['tax_object'],
                    'tax_rate' => $item['tax_rate'] ?? null,
                    'tax_amount' => $item['tax_amount'],
                    'total' => $item['total'],
                    'metadata' => $item['metadata'] ?? null,
                ]);
            }

            $request->load('items');

            $this->webhookPublisher->publish(
                $request,
                BillingWebhookPublisher::EVENT_REQUESTED
            );

            return [
                'request' => $request->load('items'),
                'replayed' => false,
            ];
        });
    }

    public function findForClient(
        ApiClient $apiClient,
        ApiKey $apiKey,
        string $externalId
    ): ApiBillingRequest {
        $billingRequest = ApiBillingRequest::query()
            ->with('items')
            ->where('api_client_id', $apiClient->id)
            ->where(
                'environment',
                strtolower((string) $apiKey->environment)
            )
            ->where('external_id', $externalId)
            ->first();

        if (!$billingRequest) {
            throw new BillingApiException(
                'No se encontró la solicitud de facturación.',
                'BILLING_REQUEST_NOT_FOUND',
                404
            );
        }

        return $billingRequest;
    }

    public function present(
        ApiBillingRequest $request,
        bool $replayed = false
    ): array {
        $documentsReady = $request->documentsReady();

        return [
            'request_id' => $request->id,
            'external_id' => $request->external_id,
            'environment' => $request->environment,
            'source_system' => $request->source_system,
            'status' => $request->status,
            'fulfillment_mode' => $request->fulfillment_mode,
            'idempotent_replay' => $replayed,
            'customer' => [
                'rfc' => $request->customer_rfc,
                'name' => $request->customer_name,
                'postal_code' =>
                    $request->customer_postal_code,
                'tax_regime' =>
                    $request->customer_tax_regime,
                'cfdi_use' => $request->customer_cfdi_use,
                'email' => $request->customer_email,
            ],
            'payment' => [
                'reference' => $request->payment_reference,
                'status' => $request->payment_status,
                'method' => $request->payment_method,
                'form' => $request->payment_form,
                'date' => $request->payment_date?->toIso8601String(),
                'currency' => $request->currency,
                'exchange_rate' => $request->exchange_rate,
            ],
            'amounts' => [
                'subtotal' => $request->subtotal,
                'discount' => $request->discount_total,
                'tax' => $request->tax_total,
                'shipping' => $request->shipping_total,
                'insurance' => $request->insurance_total,
                'total' => $request->total,
            ],
            'items' => $request->items
                ->map(fn ($item) => [
                    'line_number' => $item->line_number,
                    'client_item_id' => $item->client_item_id,
                    'category' => $item->category,
                    'product_service_code' =>
                        $item->product_service_code,
                    'unit_code' => $item->unit_code,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount' => $item->discount,
                    'subtotal' => $item->subtotal,
                    'tax_object' => $item->tax_object,
                    'tax_rate' => $item->tax_rate,
                    'tax_amount' => $item->tax_amount,
                    'total' => $item->total,
                ])
                ->values()
                ->all(),
            'invoice' => [
                'uuid' => $request->cfdi_uuid,
                'issued_at' =>
                    $request->issued_at?->toIso8601String(),
                'documents_ready' => $documentsReady,
                'documents' => $documentsReady
                    ? [
                        'pdf' => route(
                            'api.hub.billing.invoices.documents',
                            [
                                'externalId' => $request->external_id,
                                'format' => 'pdf',
                            ]
                        ),
                        'xml' => route(
                            'api.hub.billing.invoices.documents',
                            [
                                'externalId' => $request->external_id,
                                'format' => 'xml',
                            ]
                        ),
                        'zip' => route(
                            'api.hub.billing.invoices.documents',
                            [
                                'externalId' => $request->external_id,
                                'format' => 'zip',
                            ]
                        ),
                    ]
                    : null,
            ],
            'error' => $request->error_code
                || $request->error_message
                    ? [
                        'code' => $request->error_code,
                        'message' => $request->error_message,
                    ]
                    : null,
            'requested_at' =>
                $request->requested_at?->toIso8601String(),
            'updated_at' =>
                $request->updated_at?->toIso8601String(),
        ];
    }
}
