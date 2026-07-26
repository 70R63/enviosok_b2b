<?php

namespace App\Services\ApiHub\Webhooks;

use App\Models\ApiBillingRequest;
use App\Models\ApiWebhookDelivery;
use App\Models\ApiWebhookEndpoint;
use Illuminate\Support\Str;

class BillingWebhookPublisher
{
    public const EVENT_REQUESTED = 'billing.invoice.requested';
    public const EVENT_PROCESSING = 'billing.invoice.processing';
    public const EVENT_ISSUED = 'billing.invoice.issued';
    public const EVENT_REJECTED = 'billing.invoice.rejected';
    public const EVENT_CANCELLED = 'billing.invoice.cancelled';

    public function __construct(
        private readonly WebhookSigner $signer
    ) {
    }

    public static function supportedEvents(): array
    {
        return [
            self::EVENT_REQUESTED => 'Solicitud recibida',
            self::EVENT_PROCESSING => 'Atención iniciada',
            self::EVENT_ISSUED => 'CFDI emitido',
            self::EVENT_REJECTED => 'Solicitud rechazada',
            self::EVENT_CANCELLED => 'Solicitud cancelada',
        ];
    }

    public function publish(
        ApiBillingRequest $billingRequest,
        string $event
    ): int {
        if (! array_key_exists($event, self::supportedEvents())) {
            throw new \InvalidArgumentException(
                'Evento webhook de facturación no soportado.'
            );
        }

        $billingRequest->loadMissing([
            'items',
            'client.products',
        ]);

        $webhooksEnabled = $billingRequest->client
            ?->products
            ->contains(
                fn ($product): bool =>
                    $product->code === 'WEBHOOKS'
                    && (bool) $product->active
                    && (bool) $product->pivot->active
            ) ?? false;

        if (! $webhooksEnabled) {
            return 0;
        }

        $endpoints = ApiWebhookEndpoint::query()
            ->where(
                'api_client_id',
                $billingRequest->api_client_id
            )
            ->where(
                'environment',
                strtolower((string) $billingRequest->environment)
            )
            ->where('active', true)
            ->get()
            ->filter(
                fn (ApiWebhookEndpoint $endpoint): bool =>
                    $endpoint->supportsEvent($event)
            );

        $created = 0;

        foreach ($endpoints as $endpoint) {
            $exists = ApiWebhookDelivery::query()
                ->where(
                    'api_webhook_endpoint_id',
                    $endpoint->id
                )
                ->where(
                    'api_billing_request_id',
                    $billingRequest->id
                )
                ->where('event', $event)
                ->exists();

            if ($exists) {
                continue;
            }

            $eventId = (string) Str::uuid();
            $createdAt = now()->utc();
            $timestamp = $createdAt->timestamp;
            $payloadJson = $this->encodePayload(
                $this->payload(
                    $billingRequest,
                    $eventId,
                    $event,
                    $createdAt->toIso8601String()
                )
            );

            ApiWebhookDelivery::create([
                'api_webhook_endpoint_id' => $endpoint->id,
                'api_client_id' => $billingRequest->api_client_id,
                'api_billing_request_id' => $billingRequest->id,
                'event_id' => $eventId,
                'event' => $event,
                'status' => ApiWebhookDelivery::STATUS_PENDING,
                'attempts' => 0,
                'max_attempts' => 5,
                'signature_timestamp' => $timestamp,
                'signature' => $this->signer->sign(
                    $endpoint->decryptSecret(),
                    $timestamp,
                    $payloadJson
                ),
                'payload_json' => $payloadJson,
                'next_attempt_at' => now(),
            ]);

            $created++;
        }

        return $created;
    }

    private function payload(
        ApiBillingRequest $request,
        string $eventId,
        string $event,
        string $createdAt
    ): array {
        return [
            'id' => $eventId,
            'type' => $event,
            'created_at' => $createdAt,
            'environment' => $request->environment,
            'data' => [
                'invoice' => [
                    'request_id' => $request->id,
                    'external_id' => $request->external_id,
                    'source_system' => $request->source_system,
                    'status' => $request->status,
                    'fulfillment_mode' =>
                        $request->fulfillment_mode,
                    'currency' => $request->currency,
                    'total' => $request->total,
                    'payment_reference' =>
                        $request->payment_reference,
                    'customer_rfc' => $request->customer_rfc,
                    'cfdi_uuid' => $request->cfdi_uuid,
                    'documents_ready' =>
                        $request->documentsReady(),
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
                ],
            ],
        ];
    }

    private function encodePayload(array $payload): string
    {
        $json = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_PRESERVE_ZERO_FRACTION
        );

        if ($json === false) {
            throw new \RuntimeException(
                'No fue posible serializar el webhook.'
            );
        }

        return $json;
    }
}
