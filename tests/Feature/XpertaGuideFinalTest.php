<?php

namespace Tests\Feature;

use App\Models\B2cCotizacion;
use App\Services\Shipping\B2cXpertaGuideFlowService;
use App\Services\Shipping\Xperta\XpertaGuideService;
use App\Services\Shipping\Xperta\XpertaTokenService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use ReflectionMethod;
use RuntimeException;
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
                'currency' => 'NMP',
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
                'labelUrl' => 'https://labels.xperta.test/label.pdf',
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
                && $body['labelResponseOptions'] === 'URL_ONLY'
                && $body['requestedShipment']['shipper']['contact']['emailAddress'] === 'remitente@example.test'
                && $body['requestedShipment']['recipients'][0]['contact']['emailAddress'] === 'destino@example.test'
                && $body['requestedShipment']['requestedPackageLineItems'][0]['declaredValue'] === [
                    'amount' => 500.0,
                    'currency' => 'NMP',
                ];
        });
    }

    public function test_envelope_uses_existing_minimum_dimension_convention(): void
    {
        $quote = $this->quote();
        $quote->tipo_envio = 'sobre';
        $quote->medidas = null;

        $payload = app(XpertaGuideService::class)->buildPayload($quote, false);

        $this->assertSame(
            ['alto' => 0.1, 'ancho' => 0.1, 'largo' => 0.1],
            $payload['requestedShipment']['requestedPackageLineItems'][0]['dimensiones']
        );
        $this->assertSame('***TOKEN_BASE64***', $payload['token']);
    }

    public function test_label_ssrf_blocks_local_and_private_addresses(): void
    {
        config()->set('zigo_b2c_xperta.allowed_label_hosts', ['127.0.0.1']);
        $method = new ReflectionMethod(B2cXpertaGuideFlowService::class, 'assertAllowedLabelUrl');
        $method->setAccessible(true);

        $this->expectException(RuntimeException::class);
        $method->invoke(app(B2cXpertaGuideFlowService::class), 'https://127.0.0.1/label.pdf');
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
            ['labelContent' => base64_encode("%PDF-1.4\n%%EOF")]
        );

        $this->assertSame('PDF', $format);
        $this->assertStringStartsWith('private/b2c/labels/88/', $path);
        Storage::disk('local')->assertExists($path);
    }

    public function test_recovery_command_is_blocked_outside_production_without_http(): void
    {
        config()->set('services.xperta.environment', 'stage');
        Http::fake();

        $this->artisan('zigo:xperta-guide-recover', [
            'cotizacion_id' => 88,
            '--dry-run' => true,
        ])->expectsOutput('Este comando sólo puede ejecutarse en producción.')
            ->assertExitCode(1);

        Http::assertNothingSent();
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
