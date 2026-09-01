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

    public function test_payment_id_missing_is_classified(): void
    {
        $this->assertSame(Result::PAYMENT_ID_MISSING, $this->provider->webhookValidationResult(new Request(), ''));
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
}
