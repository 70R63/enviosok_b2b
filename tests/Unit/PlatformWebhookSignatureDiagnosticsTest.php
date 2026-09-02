<?php

namespace Tests\Unit;

use App\Domain\Network\Commerce\MercadoPagoPlatformPaymentProvider;
use App\Domain\Network\Commerce\PlatformWebhookValidationResult as Result;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class PlatformWebhookSignatureDiagnosticsTest extends TestCase
{
    private MercadoPagoPlatformPaymentProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'zigo_payments.platform.webhook_secret' => 'test-platform-secret',
            'zigo_payments.providers.mercado_pago.webhook_tolerance_seconds' => 300,
        ]);
        $this->provider = new MercadoPagoPlatformPaymentProvider();
    }

    public function test_body_only_webhook_is_signed_without_id_component(): void
    {
        $request = $this->signedRequest('', 'request-body-only', (string) time());

        $this->assertSame(Result::VALID_SIGNATURE, $this->provider->webhookValidationResult($request, ''));
    }

    public function test_secret_missing_is_classified(): void
    {
        config(['zigo_payments.platform.webhook_secret' => null]);
        $this->assertSame(Result::SECRET_MISSING, $this->provider->webhookValidationResult(new Request(), 'payment'));
    }

    #[DataProvider('invalidRequestProvider')]
    public function test_invalid_request_is_safely_classified(string $signature, string $requestId, string $expected): void
    {
        $request = Request::create('/webhook', 'POST', [], [], [], array_filter([
            'HTTP_X_SIGNATURE' => $signature,
            'HTTP_X_REQUEST_ID' => $requestId,
        ]));

        $this->assertSame($expected, $this->provider->webhookValidationResult($request, 'payment'));
        $this->assertFalse($this->provider->validateWebhook($request, 'payment'));
    }

    public static function invalidRequestProvider(): array
    {
        $current = 'ts='.(string) time();

        return [
            'signature header missing' => ['', 'request', Result::SIGNATURE_HEADER_MISSING],
            'request id missing' => [$current.',v1=value', '', Result::REQUEST_ID_MISSING],
            'timestamp missing' => ['v1=value', 'request', Result::TIMESTAMP_MISSING],
            'v1 missing' => [$current, 'request', Result::V1_MISSING],
            'timestamp invalid' => ['ts=not-numeric,v1=value', 'request', Result::TIMESTAMP_INVALID],
            'timestamp stale' => ['ts=1,v1=value', 'request', Result::WEBHOOK_STALE],
            'hmac mismatch' => [$current.',v1=wrong', 'request', Result::HMAC_MISMATCH],
        ];
    }

    public function test_valid_signature_is_classified_and_boolean_contract_remains_true(): void
    {
        $timestamp = (string) time();
        $paymentId = 'payment';
        $requestId = 'request';
        $manifest = "id:{$paymentId};request-id:{$requestId};ts:{$timestamp};";
        $signature = hash_hmac('sha256', $manifest, 'test-platform-secret');
        $request = Request::create('/webhook', 'POST', [], [], [], [
            'HTTP_X_SIGNATURE' => "ts={$timestamp},v1={$signature}",
            'HTTP_X_REQUEST_ID' => $requestId,
        ]);

        $this->assertSame(Result::VALID_SIGNATURE, $this->provider->webhookValidationResult($request, $paymentId));
        $this->assertTrue($this->provider->validateWebhook($request, $paymentId));
    }

    public function test_signature_data_id_is_lowercased_in_official_manifest(): void
    {
        $request = $this->signedRequest('Payment-ABC', 'Request-Preserved', (string) time());

        $this->assertSame(Result::VALID_SIGNATURE, $this->provider->webhookValidationResult($request, 'Payment-ABC'));
    }

    public function test_millisecond_timestamp_uses_original_value_in_manifest_and_seconds_for_freshness(): void
    {
        $timestamp = (string) (time() * 1000);
        $request = $this->signedRequest('123456', 'request-ms', $timestamp);

        $this->assertSame(Result::VALID_SIGNATURE, $this->provider->webhookValidationResult($request, '123456'));
    }

    public function test_body_only_signature_incorrectly_including_id_is_rejected(): void
    {
        $timestamp = (string) time();
        $manifest = "id:body-payment;request-id:request-body;ts:{$timestamp};";
        $signature = hash_hmac('sha256', $manifest, 'test-platform-secret');
        $request = Request::create('/webhook', 'POST', [], [], [], [
            'HTTP_X_SIGNATURE' => "ts={$timestamp},v1={$signature}",
            'HTTP_X_REQUEST_ID' => 'request-body',
        ]);

        $this->assertSame(Result::HMAC_MISMATCH, $this->provider->webhookValidationResult($request, ''));
    }

    private function signedRequest(string $signatureDataId, string $requestId, string $timestamp): Request
    {
        $manifest = $signatureDataId !== '' ? 'id:'.strtolower($signatureDataId).';' : '';
        $manifest .= "request-id:{$requestId};ts:{$timestamp};";
        $signature = hash_hmac('sha256', $manifest, 'test-platform-secret');

        return Request::create('/webhook', 'POST', [], [], [], [
            'HTTP_X_SIGNATURE' => "ts={$timestamp},v1={$signature}",
            'HTTP_X_REQUEST_ID' => $requestId,
        ]);
    }
}
