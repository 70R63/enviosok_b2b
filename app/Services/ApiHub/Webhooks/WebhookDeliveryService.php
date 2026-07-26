<?php

namespace App\Services\ApiHub\Webhooks;

use App\Models\ApiWebhookDelivery;
use App\Models\ApiWebhookDeliveryAttempt;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class WebhookDeliveryService
{
    private const RETRY_DELAYS_MINUTES = [
        1 => 1,
        2 => 5,
        3 => 15,
        4 => 60,
    ];

    public function __construct(
        private readonly WebhookSigner $signer,
        private readonly WebhookUrlGuard $urlGuard
    ) {
    }

    public function processDue(int $limit = 50): array
    {
        $limit = max(1, min($limit, 500));

        $ids = ApiWebhookDelivery::query()
            ->whereIn('status', [
                ApiWebhookDelivery::STATUS_PENDING,
                ApiWebhookDelivery::STATUS_RETRY,
            ])
            ->whereColumn('attempts', '<', 'max_attempts')
            ->where(function ($query): void {
                $query->whereNull('next_attempt_at')
                    ->orWhere('next_attempt_at', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('locked_at')
                    ->orWhere(
                        'locked_at',
                        '<=',
                        now()->subMinutes(
                            (int) config(
                                'services.zigo_webhooks.stale_lock_minutes',
                                5
                            )
                        )
                    );
            })
            ->whereHas('endpoint', function ($query): void {
                $query->where('active', true);
            })
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        $result = [
            'selected' => $ids->count(),
            'delivered' => 0,
            'retry' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        foreach ($ids as $id) {
            $status = $this->deliver((int) $id);

            if (array_key_exists($status, $result)) {
                $result[$status]++;
            } else {
                $result['skipped']++;
            }
        }

        return $result;
    }

    public function deliver(int $deliveryId): string
    {
        $claim = $this->claim($deliveryId);

        if ($claim === null) {
            return 'skipped';
        }

        /** @var ApiWebhookDelivery $delivery */
        $delivery = $claim['delivery'];
        /** @var ApiWebhookDeliveryAttempt $attempt */
        $attempt = $claim['attempt'];
        $headers = $claim['headers'];
        $startedAt = microtime(true);

        try {
            $this->urlGuard->assertAllowed(
                $delivery->endpoint->url,
                $delivery->endpoint->environment
            );

            $response = Http::withOptions([
                'connect_timeout' => (float) config(
                    'services.zigo_webhooks.connect_timeout',
                    5
                ),
                'timeout' => (float) config(
                    'services.zigo_webhooks.timeout',
                    10
                ),
                'allow_redirects' => false,
                'http_errors' => false,
            ])
                ->withHeaders($headers)
                ->withBody(
                    $delivery->payload_json,
                    'application/json'
                )
                ->post($delivery->endpoint->url);

            return $this->completeFromResponse(
                $delivery,
                $attempt,
                $response,
                $startedAt
            );
        } catch (Throwable $exception) {
            return $this->completeFromException(
                $delivery,
                $attempt,
                $exception,
                $startedAt
            );
        }
    }

    public function requeue(ApiWebhookDelivery $delivery): void
    {
        DB::transaction(function () use ($delivery): void {
            $locked = ApiWebhookDelivery::query()
                ->lockForUpdate()
                ->findOrFail($delivery->id);

            if ($locked->locked_at !== null) {
                throw new \RuntimeException(
                    'La entrega está siendo procesada actualmente.'
                );
            }

            $locked->update([
                'status' => ApiWebhookDelivery::STATUS_PENDING,
                'max_attempts' => max(
                    $locked->max_attempts,
                    $locked->attempts + 5
                ),
                'next_attempt_at' => now(),
                'delivered_at' => null,
                'response_status' => null,
                'response_body' => null,
                'error_message' => null,
                'lock_token' => null,
                'locked_at' => null,
            ]);
        });
    }

    private function claim(int $deliveryId): ?array
    {
        return DB::transaction(function () use ($deliveryId): ?array {
            $delivery = ApiWebhookDelivery::query()
                ->with([
                    'endpoint.client.products',
                ])
                ->lockForUpdate()
                ->find($deliveryId);

            if ($delivery === null || ! $this->isEligible($delivery)) {
                return null;
            }

            $webhooksEnabled = $delivery->endpoint
                ->client
                ?->products
                ->contains(
                    fn ($product): bool =>
                        $product->code === 'WEBHOOKS'
                        && (bool) $product->active
                        && (bool) $product->pivot->active
                ) ?? false;

            if (! $webhooksEnabled) {
                return null;
            }

            $attemptNumber = $delivery->attempts + 1;
            $timestamp = now()->utc()->timestamp;
            $signature = $this->signer->sign(
                $delivery->endpoint->decryptSecret(),
                $timestamp,
                $delivery->payload_json
            );
            $lockToken = (string) Str::uuid();
            $headers = $this->requestHeaders(
                $delivery,
                $timestamp,
                $signature
            );

            $delivery->update([
                'attempts' => $attemptNumber,
                'signature_timestamp' => $timestamp,
                'signature' => $signature,
                'last_attempt_at' => now(),
                'lock_token' => $lockToken,
                'locked_at' => now(),
            ]);

            $attempt = ApiWebhookDeliveryAttempt::create([
                'api_webhook_delivery_id' => $delivery->id,
                'attempt_number' => $attemptNumber,
                'started_at' => now(),
                'request_url' => $delivery->endpoint->url,
                'request_headers_json' => $headers,
                'successful' => false,
            ]);

            $delivery->setAttribute('lock_token', $lockToken);

            return compact('delivery', 'attempt', 'headers');
        });
    }

    private function isEligible(ApiWebhookDelivery $delivery): bool
    {
        if (! in_array($delivery->status, [
            ApiWebhookDelivery::STATUS_PENDING,
            ApiWebhookDelivery::STATUS_RETRY,
        ], true)) {
            return false;
        }

        if ($delivery->attempts >= $delivery->max_attempts) {
            return false;
        }

        if (
            $delivery->next_attempt_at !== null
            && $delivery->next_attempt_at->isFuture()
        ) {
            return false;
        }

        if (! $delivery->endpoint?->active) {
            return false;
        }

        if ($delivery->locked_at === null) {
            return true;
        }

        return $delivery->locked_at->lte(
            now()->subMinutes(
                (int) config(
                    'services.zigo_webhooks.stale_lock_minutes',
                    5
                )
            )
        );
    }

    private function requestHeaders(
        ApiWebhookDelivery $delivery,
        int $timestamp,
        string $signature
    ): array {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'User-Agent' => (string) config(
                'services.zigo_webhooks.user_agent',
                'ZIGO-Webhook/1.0'
            ),
            'X-ZIGO-Event' => $delivery->event,
            'X-ZIGO-Delivery-ID' => $delivery->event_id,
            'X-ZIGO-Timestamp' => (string) $timestamp,
            'X-ZIGO-Signature' => $signature,
            'X-ZIGO-Environment' =>
                $delivery->endpoint->environment,
        ];
    }

    private function completeFromResponse(
        ApiWebhookDelivery $delivery,
        ApiWebhookDeliveryAttempt $attempt,
        Response $response,
        float $startedAt
    ): string {
        $successful = $response->successful();
        $duration = $this->durationMs($startedAt);
        $body = $this->truncate($response->body());
        $headers = $response->headers();

        $attempt->update([
            'finished_at' => now(),
            'duration_ms' => $duration,
            'response_status' => $response->status(),
            'response_headers_json' => $headers,
            'response_body' => $body,
            'error_message' => $successful
                ? null
                : 'El receptor respondió HTTP '
                    . $response->status() . '.',
            'successful' => $successful,
        ]);

        return $this->finalize(
            $delivery,
            $successful,
            $response->status(),
            $body,
            $successful
                ? null
                : 'El receptor respondió HTTP '
                    . $response->status() . '.',
            $duration
        );
    }

    private function completeFromException(
        ApiWebhookDelivery $delivery,
        ApiWebhookDeliveryAttempt $attempt,
        Throwable $exception,
        float $startedAt
    ): string {
        $duration = $this->durationMs($startedAt);
        $message = $this->truncate(
            $exception->getMessage(),
            2000
        );

        $attempt->update([
            'finished_at' => now(),
            'duration_ms' => $duration,
            'error_message' => $message,
            'successful' => false,
        ]);

        return $this->finalize(
            $delivery,
            false,
            null,
            null,
            $message,
            $duration
        );
    }

    private function finalize(
        ApiWebhookDelivery $delivery,
        bool $successful,
        ?int $responseStatus,
        ?string $responseBody,
        ?string $errorMessage,
        int $durationMs
    ): string {
        return DB::transaction(function () use (
            $delivery,
            $successful,
            $responseStatus,
            $responseBody,
            $errorMessage,
            $durationMs
        ): string {
            $locked = ApiWebhookDelivery::query()
                ->lockForUpdate()
                ->findOrFail($delivery->id);

            if ($locked->lock_token !== $delivery->lock_token) {
                return 'skipped';
            }

            if ($successful) {
                $locked->update([
                    'status' => ApiWebhookDelivery::STATUS_DELIVERED,
                    'next_attempt_at' => null,
                    'delivered_at' => now(),
                    'response_status' => $responseStatus,
                    'response_body' => $responseBody,
                    'error_message' => null,
                    'response_time_ms' => $durationMs,
                    'lock_token' => null,
                    'locked_at' => null,
                ]);

                $locked->endpoint()->update([
                    'last_delivery_at' => now(),
                ]);

                return 'delivered';
            }

            $finalFailure = $locked->attempts >= $locked->max_attempts;
            $status = $finalFailure
                ? ApiWebhookDelivery::STATUS_FAILED
                : ApiWebhookDelivery::STATUS_RETRY;

            $locked->update([
                'status' => $status,
                'next_attempt_at' => $finalFailure
                    ? null
                    : now()->addMinutes(
                        $this->retryDelayMinutes(
                            $locked->attempts
                        )
                    ),
                'response_status' => $responseStatus,
                'response_body' => $responseBody,
                'error_message' => $errorMessage,
                'response_time_ms' => $durationMs,
                'lock_token' => null,
                'locked_at' => null,
            ]);

            return $finalFailure ? 'failed' : 'retry';
        });
    }

    private function retryDelayMinutes(int $attempt): int
    {
        return self::RETRY_DELAYS_MINUTES[$attempt]
            ?? 180;
    }

    private function durationMs(float $startedAt): int
    {
        return max(
            0,
            (int) round((microtime(true) - $startedAt) * 1000)
        );
    }

    private function truncate(
        ?string $value,
        ?int $limit = null
    ): ?string {
        if ($value === null) {
            return null;
        }

        $max = $limit ?? (int) config(
            'services.zigo_webhooks.max_response_body',
            10000
        );

        return mb_substr($value, 0, max(1, $max));
    }
}
