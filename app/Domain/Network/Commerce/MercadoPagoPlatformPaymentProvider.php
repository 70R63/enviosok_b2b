<?php

namespace App\Domain\Network\Commerce;

use App\Domain\Network\Commerce\Contracts\PlatformPaymentProvider;
use App\Domain\Network\Commerce\Models\{PlatformPaymentAttempt, TenantSaasOrder};
use App\Domain\Network\Onboarding\Models\SaasOnboardingApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class MercadoPagoPlatformPaymentProvider implements PlatformPaymentProvider
{
    private function cfg(string $key, mixed $default = null): mixed
    {
        return config('zigo_payments.'.$key, $default);
    }

    public function accountId(): string
    {
        return (string) $this->cfg('platform.account_id');
    }

    public function createCheckout(PlatformPaymentAttempt $attempt, TenantSaasOrder $order): array
    {
        $return = url('/admin/compras/'.$order->uuid.'/retorno');

        return $this->createPreference($attempt, [
            'id' => $order->purchase_snapshot['code'],
            'title' => $order->purchase_snapshot['name'],
            'amount' => (string) $order->total_amount,
            'currency' => $order->currency,
            'return_urls' => [
                'success' => $return.'/success',
                'pending' => $return.'/pending',
                'failure' => $return.'/failure',
            ],
            'notification_query' => 'saas_attempt='.$attempt->uuid,
            'expires_at' => $order->expires_at,
        ]);
    }

    public function createOnboardingCheckout(
        PlatformPaymentAttempt $attempt,
        SaasOnboardingApplication $application,
    ): array {
        $snapshot = $application->commercial_snapshot_json;
        if (!$snapshot || !$application->total || !$application->currency) {
            throw new RuntimeException('ONBOARDING_SNAPSHOT_REQUIRED');
        }

        $return = route('zigo-platform.onboarding.return', [
            'token' => $application->public_token,
            'result' => '__RESULT__',
        ]);

        return $this->createPreference($attempt, [
            'id' => 'ONBOARDING-'.$application->uuid,
            'title' => 'ZIGO Platform - '.$snapshot['plan']['name'],
            'amount' => (string) $application->total,
            'currency' => $application->currency,
            'return_urls' => [
                'success' => str_replace('__RESULT__', 'success', $return),
                'pending' => str_replace('__RESULT__', 'pending', $return),
                'failure' => str_replace('__RESULT__', 'failure', $return),
            ],
            // Processing this notification is deliberately deferred to ZN-11B.4.
            'notification_query' => 'onboarding_attempt='.$attempt->uuid,
            'expires_at' => $application->subdomain_reserved_until,
        ]);
    }

    private function createPreference(PlatformPaymentAttempt $attempt, array $checkout): array
    {
        $token = (string) $this->cfg('platform.access_token');
        $webhook = (string) $this->cfg('platform.webhook_url');
        if (!$token || !$webhook) {
            throw new RuntimeException('PLATFORM_PAYMENT_CONFIGURATION_MISSING');
        }

        $separator = str_contains($webhook, '?') ? '&' : '?';
        $body = [
            'items' => [[
                'id' => $checkout['id'],
                'title' => $checkout['title'],
                'quantity' => 1,
                'currency_id' => $checkout['currency'],
                'unit_price' => (float) $checkout['amount'],
            ]],
            'external_reference' => $attempt->external_reference,
            'back_urls' => $checkout['return_urls'],
            'auto_return' => 'approved',
            'notification_url' => $webhook.$separator.$checkout['notification_query'],
            'expires' => true,
            'expiration_date_to' => $checkout['expires_at']?->toIso8601String(),
        ];

        $response = Http::acceptJson()->asJson()->withToken($token)
            ->withHeaders(['X-Idempotency-Key' => $attempt->uuid])
            ->post(
                rtrim((string) $this->cfg('providers.mercado_pago.api_url'), '/').'/checkout/preferences',
                $body,
            );
        if (!$response->successful()) {
            throw new RuntimeException('MERCADO_PAGO_PREFERENCE_HTTP_'.$response->status());
        }

        return $response->json();
    }

    public function retrievePayment(string $id): array
    {
        $response = Http::acceptJson()->withToken((string) $this->cfg('platform.access_token'))
            ->get(rtrim((string) $this->cfg('providers.mercado_pago.api_url'), '/').'/v1/payments/'.rawurlencode($id));
        if (!$response->successful()) {
            throw new RuntimeException('No fue posible verificar el pago SaaS.');
        }
        return $response->json();
    }

    public function validateWebhook(Request $request, string $signatureDataId): bool
    {
        return $this->webhookValidationResult($request, $signatureDataId) === PlatformWebhookValidationResult::VALID_SIGNATURE;
    }

    public function webhookValidationResult(Request $request, string $signatureDataId): string
    {
        $secret = (string) $this->cfg('platform.webhook_secret');
        $signature = (string) $request->header('x-signature');
        $requestId = (string) $request->header('x-request-id');
        if ($secret === '') {
            return PlatformWebhookValidationResult::SECRET_MISSING;
        }
        if ($signature === '') {
            return PlatformWebhookValidationResult::SIGNATURE_HEADER_MISSING;
        }
        if ($requestId === '') {
            return PlatformWebhookValidationResult::REQUEST_ID_MISSING;
        }

        preg_match('/(?:^|,)\s*ts=([^,]+)/', $signature, $timestampMatch);
        preg_match('/(?:^|,)\s*v1=([^,]+)/', $signature, $signatureMatch);
        if (!isset($timestampMatch[1])) {
            return PlatformWebhookValidationResult::TIMESTAMP_MISSING;
        }
        if (!isset($signatureMatch[1])) {
            return PlatformWebhookValidationResult::V1_MISSING;
        }
        if (!ctype_digit($timestampMatch[1])) {
            return PlatformWebhookValidationResult::TIMESTAMP_INVALID;
        }
        $issued = (int) $timestampMatch[1];
        if ($issued > 99999999999) {
            $issued = (int) floor($issued / 1000);
        }
        if (abs(time() - $issued) > (int) $this->cfg('providers.mercado_pago.webhook_tolerance_seconds', 300)) {
            return PlatformWebhookValidationResult::WEBHOOK_STALE;
        }
        $manifest = $signatureDataId !== '' ? 'id:'.strtolower($signatureDataId).';' : '';
        $manifest .= 'request-id:'.$requestId.';ts:'.$timestampMatch[1].';';
        return hash_equals(hash_hmac('sha256', $manifest, $secret), trim($signatureMatch[1]))
            ? PlatformWebhookValidationResult::VALID_SIGNATURE
            : PlatformWebhookValidationResult::HMAC_MISMATCH;
    }
}
