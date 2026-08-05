<?php

namespace Tests\Feature;

use App\Models\B2cCotizacion;
use App\Console\Commands\RecoverXpertaB2cGuide;
use App\Http\Controllers\API\Payments\MercadoPagoWebhookController;
use App\Services\Shipping\B2cXpertaGuideFlowService;
use App\Services\Shipping\Xperta\XpertaGuideService;
use App\Services\Shipping\Xperta\XpertaTokenService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use ReflectionMethod;
use Tests\TestCase;

final class XpertaGuideFinalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.xperta', array_merge(
            (array) config('services.xperta'),
            [
                'enabled' => true,
                'guide_enabled' => true,
                'environment' => 'production',
                'base_url' => 'https://api.xperta.test',
                'empresa' => 'empresa-01',
                'ltd' => 'estafeta',
                'corporativo' => 'corporativo-01',
                'email' => 'api@example.test',
                'password' => 'secret-password',
                'api_key' => 'secret-api-key',
                'guide_path' => '/api/v1/empresas/{empresa}/ltds/{ltd}/servicios/{service}/guia',
                'guide_empresa_id' => '42',
            ]
        ));
        config()->set('zigo_b2c_xperta.guide_enabled', true);
        Cache::put($this->cacheKey(), '99|GUIDE-TOKEN', 60);
    }

    /** @dataProvider services */
    public function test_exact_url_headers_and_base64_payload(string $service): void
    {
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'trackingNumber' => 'TRACK-123',
                'pdfBase64' => base64_encode("%PDF-1.4\n%%EOF"),
            ]),
        ]);

        app(XpertaGuideService::class)->createWithMeta(
            $this->quote(),
            $service
        );

        Http::assertSent(function ($request) use ($service): bool {
            $body = $request->data();
            return $request->method() === 'POST'
                && $request->url() === "https://api.xperta.test/api/v1/empresas/empresa-01/ltds/estafeta/servicios/{$service}/guia"
                && $request->header('Corporativo')[0] === 'corporativo-01'
                && $request->header('x-api-key')[0] === 'secret-api-key'
                && $body['token'] === base64_encode('99|GUIDE-TOKEN')
                && base64_decode($body['token'], true) === '99|GUIDE-TOKEN'
                && $body['empresa_id'] === 42
                && $body['name'] === 'remitente@example.test'
                && !isset($body['email'], $body['password'], $body['requestedShipment'], $body['labelResponseOptions'])
                && $body['labelDefinition']['itemDescription'] === [
                    'parcelId' => 4, 'weight' => '3', 'height' => '15', 'length' => '25', 'width' => '20',
                ]
                && $body['labelDefinition']['serviceConfiguration']['isInsurance'] === true
                && $body['labelDefinition']['serviceConfiguration']['insurance'] === 500.0
                && preg_match('/^\d{8}$/', $body['labelDefinition']['serviceConfiguration']['effectiveDate']) === 1
                && $body['labelDefinition']['location']['notified']['residence']['contact']['email'] === 'destino@example.test';
        });
    }

    public function test_envelope_uses_existing_minimum_dimension_convention(): void
    {
        $quote = $this->quote();
        $quote->tipo_envio = 'sobre';
        $quote->medidas = null;

        $payload = app(XpertaGuideService::class)->buildPayload($quote, false);

        $this->assertSame(
            ['parcelId' => 4, 'weight' => '3', 'height' => '0.1', 'length' => '0.1', 'width' => '0.1'],
            $payload['labelDefinition']['itemDescription']
        );
        $this->assertSame('***TOKEN_BASE64***', $payload['token']);
    }

    public function test_empty_empresa_id_is_json_null(): void
    {
        config()->set('services.xperta.guide_empresa_id', '');
        $payload = app(XpertaGuideService::class)->buildPayload($this->quote(), false);
        $this->assertNull($payload['empresa_id']);
        $this->assertJson(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function test_without_insurance_sends_false_and_null(): void
    {
        $quote = $this->quote();
        $quote->requiere_seguro_envio = false;
        $payload = app(XpertaGuideService::class)->buildPayload($quote, false);
        $configuration = $payload['labelDefinition']['serviceConfiguration'];
        $this->assertFalse($configuration['isInsurance']);
        $this->assertNull($configuration['insurance']);
        $this->assertNull($payload['labelDefinition']['location']['notified']['residence']['address']['addressReference']);
    }

    public function test_document_url_is_not_downloaded_and_requires_review(): void
    {
        Http::fake();
        $method = new ReflectionMethod(B2cXpertaGuideFlowService::class, 'storeLabel');
        $method->setAccessible(true);
        try {
            $method->invoke(app(B2cXpertaGuideFlowService::class), $this->quote(), [
                'documentUrl' => 'https://unknown.example.test/guide.pdf',
            ]);
            $this->fail('La URL debió requerir revisión.');
        } catch (\ReflectionException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            $cause = $exception->getPrevious() ?: $exception;
            $this->assertStringStartsWith('GUIDE_RESPONSE_REQUIRES_REVIEW', $cause->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_invalid_base64_document_is_rejected(): void
    {
        $method = new ReflectionMethod(B2cXpertaGuideFlowService::class, 'storeLabel');
        $method->setAccessible(true);
        $this->assertSame([null, null], $method->invoke(
            app(B2cXpertaGuideFlowService::class), $this->quote(), ['pdfBase64' => base64_encode('not a pdf')]
        ));
    }

    public function test_base64_label_is_validated_and_stored_privately(): void
    {
        Storage::fake('local');
        $quote = $this->quote();
        $quote->id = 88;
        $method = new ReflectionMethod(B2cXpertaGuideFlowService::class, 'storeLabel');
        $method->setAccessible(true);

        [$path, $format] = $method->invoke(
            app(B2cXpertaGuideFlowService::class),
            $quote,
            ['pdfBase64' => base64_encode("%PDF-1.4\n%%EOF")]
        );

        $this->assertSame('PDF', $format);
        $this->assertStringStartsWith('private/b2c/labels/88/', $path);
        Storage::disk('local')->assertExists($path);
    }

    /** @dataProvider recoverableEnvironments */
    public function test_recovery_environment_is_allowed(string $environment): void
    {
        config()->set('services.xperta.environment', $environment);
        $method = new ReflectionMethod(RecoverXpertaB2cGuide::class, 'environmentAllowed');
        $method->setAccessible(true);
        $this->assertTrue($method->invoke(new RecoverXpertaB2cGuide()));
    }

    public function test_recovery_environment_blocks_local(): void
    {
        config()->set('services.xperta.environment', 'local');
        $method = new ReflectionMethod(RecoverXpertaB2cGuide::class, 'environmentAllowed');
        $method->setAccessible(true);
        $this->assertFalse($method->invoke(new RecoverXpertaB2cGuide()));
    }

    public function test_approved_verified_payment_is_selected_for_automatic_guide(): void
    {
        $quote = $this->quote();
        $quote->forceFill(['payment_status' => 'approved', 'payment_verified_at' => now(), 'provider' => 'xperta', 'carrier' => 'estafeta']);
        $quote->shouldReceive('hasGeneratedGuide')->andReturnFalse();
        $method = new ReflectionMethod(MercadoPagoWebhookController::class, 'shouldGenerateXpertaGuide');
        $method->setAccessible(true);
        $this->assertTrue($method->invoke(new MercadoPagoWebhookController(), $quote));
        $quote->payment_verified_at = null;
        $this->assertFalse($method->invoke(new MercadoPagoWebhookController(), $quote));
    }

    public function test_disabled_guide_flag_logs_specific_reason_without_secrets(): void
    {
        config()->set('services.xperta.guide_enabled', false);
        Log::spy();
        $quote = $this->quote();
        $quote->id = 100;
        try {
            app(B2cXpertaGuideFlowService::class)->generateAfterConfirmedPayment($quote);
            $this->fail('La bandera apagada debió rechazar la generación.');
        } catch (\RuntimeException $exception) {
            $this->assertStringStartsWith('GUIDE_FLAG_DISABLED:', $exception->getMessage());
        }
        Log::shouldHaveReceived('warning')->withArgs(function ($message, $context): bool {
            return $context['reason_code'] === 'GUIDE_FLAG_DISABLED'
                && !isset($context['token'], $context['password'], $context['api_key']);
        })->once();
    }

    public function test_public_templates_do_not_expose_secrets_or_remote_label_url(): void
    {
        foreach ([
            resource_path('views/b2c/pago-estado.blade.php'),
            resource_path('views/b2c/mis-envios.blade.php'),
            resource_path('views/b2c/detalle-envio.blade.php'),
        ] as $path) {
            $html = (string) file_get_contents($path);
            $this->assertStringNotContainsString('guia_response_snapshot', $html);
            $this->assertStringNotContainsString('labelUrl', $html);
            $this->assertStringNotContainsString('x-api-key', $html);
            $this->assertStringNotContainsString('token', strtolower($html));
        }
    }

    public static function services(): array
    {
        return [['terrestre'], ['diasig']];
    }

    public static function recoverableEnvironments(): array
    {
        return [['production'], ['stage'], ['staging']];
    }

    private function quote(): B2cCotizacion
    {
        $quote = \Mockery::mock(B2cCotizacion::class)->makePartial();
        $quote->shouldReceive('hasCompleteShippingAddresses')->andReturnTrue();
        $quote->shouldReceive('hasCompletePackageData')->andReturnTrue();
        $quote->forceFill([
            'tipo_envio' => 'caja', 'medidas' => '25x20x15', 'peso' => 2,
            'peso_facturable' => 3, 'valor_declarado' => 500,
            'requiere_seguro_envio' => true, 'contenido' => 'Ropa',
            'remitente_nombre' => 'Remitente', 'remitente_telefono' => '5512345678',
            'remitente_email' => 'remitente@example.test', 'remitente_direccion' => 'Calle Uno',
            'remitente_num_ext' => '10', 'remitente_num_int' => null,
            'colonia_origen' => 'Centro', 'ciudad_origen' => 'México',
            'estado_origen' => 'Ciudad de México', 'cp_origen' => '09800',
            'destinatario_nombre' => 'Destino', 'destinatario_telefono' => '5587654321',
            'destinatario_email' => 'destino@example.test', 'destinatario_direccion' => 'Calle Dos',
            'destinatario_num_ext' => '20', 'destinatario_num_int' => '2',
            'colonia_destino' => 'Centro', 'ciudad_destino' => 'Neza',
            'estado_destino' => 'México', 'cp_destino' => '57820',
        ]);
        return $quote;
    }

    private function cacheKey(): string
    {
        return 'xperta:token:' . hash('sha256', implode('|', [
            config('services.xperta.base_url'),
            config('services.xperta.corporativo'),
            config('services.xperta.email'),
        ]));
    }
}
