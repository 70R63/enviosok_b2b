<?php

namespace Tests\Feature;

use App\Domain\ApiHub\ApiWebhookDeliveryService;
use App\Domain\Shipping\LastMile\DeliveryEvidenceService;
use App\Services\Security\StageSafetyGuard;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use ReflectionMethod;
use Tests\TestCase;

final class ZigoSecurityReleaseGateTest extends TestCase
{
    public function test_global_security_headers_are_present(): void
    {
        $this->get('/login')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy')
            ->assertHeader('Content-Security-Policy-Report-Only');
    }

    public function test_logout_is_not_globally_exempt_from_csrf(): void
    {
        $middleware = file_get_contents(app_path('Http/Middleware/VerifyCsrfToken.php'));
        $this->assertStringNotContainsString('"logout"', $middleware);
        $this->assertStringContainsString('protected $except = [];', $middleware);
    }

    public function test_legacy_api_webhook_delivery_cannot_follow_redirects(): void
    {
        $source = file_get_contents((new \ReflectionClass(ApiWebhookDeliveryService::class))->getFileName());
        $this->assertStringContainsString("'allow_redirects'=>false", $source);
    }

    public function test_pod_image_is_reencoded_before_private_storage(): void
    {
        Storage::fake('local');
        $upload = UploadedFile::fake()->image('proof.jpg', 32, 32);
        $service = app(DeliveryEvidenceService::class);
        $method = new ReflectionMethod($service, 'storeUpload');
        $method->setAccessible(true);

        $path = $method->invoke($service, $upload, 'pod/photos');
        Storage::disk('local')->assertExists($path);
        $this->assertSame('image/jpeg', (new \finfo(FILEINFO_MIME_TYPE))->buffer(Storage::disk('local')->get($path)));
    }

    public function test_example_environment_contains_no_application_key(): void
    {
        $line = collect(file(base_path('.env.example'), FILE_IGNORE_NEW_LINES))
            ->first(fn (string $line): bool => str_starts_with($line, 'APP_KEY='));
        $this->assertSame('APP_KEY=', $line);
    }

    public function test_stage_gate_rejects_mercado_pago_production_mode(): void
    {
        $previous = $this->app->environment();
        $this->app['env'] = 'staging';
        config([
            'app.debug' => false,
            'app.url' => 'https://stage.zigo-envios.com',
            'session.secure' => true,
            'session.domain' => null,
            'cache.default' => 'file',
            'zigo_driver.url' => 'https://driver-stage.zigo-envios.com',
            'zigo_surfaces.network.url' => 'https://network-stage.zigo-envios.com',
            'zigo_surfaces.payments.url' => 'https://payments-stage.zigo-envios.com',
            'zigo_api_hub.url' => 'https://api-stage.zigo-envios.com',
            'zigo_payments.providers.mercado_pago.enabled' => true,
            'zigo_payments.providers.mercado_pago.environment' => 'production',
        ]);

        try {
            $this->expectException(\LogicException::class);
            app(StageSafetyGuard::class)->enforce();
        } finally {
            $this->app['env'] = $previous;
        }
    }
}
