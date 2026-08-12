<?php

namespace Tests\Feature;

use App\Domain\Network\Commerce\MercadoPagoPlatformPaymentProvider;
use App\Domain\Payments\MercadoPagoPaymentProvider;
use App\Http\Controllers\Payments\MercadoPagoPlatformWebhookController;
use App\Http\Controllers\Payments\MercadoPagoWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MercadoPagoWebhookRoutingTest extends TestCase
{
    public function test_seller_and_platform_routes_have_explicit_controllers(): void
    {
        $this->assertSame(
            MercadoPagoWebhookController::class,
            Route::getRoutes()->getByName('payments.mercado-pago.webhook')->getControllerClass(),
        );
        $this->assertSame(
            MercadoPagoPlatformWebhookController::class,
            Route::getRoutes()->getByName('payments.mercado-pago.platform.webhook')->getControllerClass(),
        );
    }

    public function test_seller_and_platform_webhook_secrets_are_not_interchangeable(): void
    {
        config([
            'zigo_payments.providers.mercado_pago.webhook_secret' => 'seller-secret',
            'zigo_payments.platform.webhook_secret' => 'platform-secret',
            'zigo_payments.providers.mercado_pago.webhook_tolerance_seconds' => 300,
        ]);
        $paymentId = 'payment-123';
        $requestId = 'request-123';
        $timestamp = (string) time();

        $sellerRequest = $this->signedRequest($paymentId, $requestId, $timestamp, 'seller-secret');
        $platformRequest = $this->signedRequest($paymentId, $requestId, $timestamp, 'platform-secret');
        $seller = new MercadoPagoPaymentProvider();
        $platform = new MercadoPagoPlatformPaymentProvider();

        $this->assertTrue($seller->validateWebhook($sellerRequest, $paymentId));
        $this->assertFalse($platform->validateWebhook($sellerRequest, $paymentId));
        $this->assertTrue($platform->validateWebhook($platformRequest, $paymentId));
        $this->assertFalse($seller->validateWebhook($platformRequest, $paymentId));
    }

    private function signedRequest(string $paymentId, string $requestId, string $timestamp, string $secret): Request
    {
        $manifest = "id:{$paymentId};request-id:{$requestId};ts:{$timestamp};";
        $signature = hash_hmac('sha256', $manifest, $secret);

        return Request::create('/api/payments/mercado-pago/webhook', 'POST', [], [], [], [
            'HTTP_X_REQUEST_ID' => $requestId,
            'HTTP_X_SIGNATURE' => "ts={$timestamp},v1={$signature}",
        ]);
    }
}
