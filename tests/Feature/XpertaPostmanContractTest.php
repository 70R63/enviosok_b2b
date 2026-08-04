<?php

namespace Tests\Feature;

use App\Http\Controllers\B2C\CotizacionPublicaController;
use App\Models\B2cCotizacion;
use App\Services\Shipping\Data\UnifiedQuoteRequest;
use App\Services\Shipping\Providers\XpertaEstafetaQuoteProvider;
use App\Services\Shipping\Xperta\XpertaFrequencyService;
use App\Services\Shipping\Xperta\XpertaGuideService;
use App\Services\Shipping\Xperta\XpertaTokenService;
use App\Services\ZigoProviderRateService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class XpertaPostmanContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false],
            'services.shipping.unified_quote_enabled' => false,
            'services.shipping.prd_quote_probe_enabled' => false,
            'services.xperta.base_url' => 'https://xperta.test',
            'services.xperta.corporativo' => 'corp-real',
            'services.xperta.empresa' => 'empresa-no-usar',
            'services.xperta.ltd' => 'estafeta',
            'services.xperta.email' => 'secret@example.test',
            'services.xperta.password' => 'secret-password',
            'services.xperta.api_key' => 'secret-api-key',
            'services.xperta.token_minutes' => 60,
            'services.xperta.token_path' => '/api/v1/{corporativo}/login',
            'services.xperta.frequency_enabled' => true,
            'services.xperta.frequency_method' => 'POST',
            'services.xperta.frequency_path' => '/api/v1/empresas/{corporativo}/ltds/{ltd}/frecuencia/{origin}/{destination}',
            'services.xperta.quote_method' => 'POST',
            'services.xperta.quote_path' => '/api/v1/empresas/{corporativo}/ltds/{ltd}/servicios/{service}/cotizaciones',
            'services.xperta.services' => ['terrestre'],
            'services.xperta.include_declared_value_in_quote' => true,
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Schema::create('b2c_cotizaciones', fn (Blueprint $table) => $table->id());
        Cache::flush();
    }

    public function test_frequency_uses_corporativo_path_headers_and_token_only_body(): void
    {
        Cache::put($this->cacheKey(), '123|raw-token', 60);
        Http::fake(['*' => Http::response(['success' => true, 'data' => []], 200)]);
        $before = DB::table('b2c_cotizaciones')->count();

        app(XpertaFrequencyService::class)->check('09800', '57820');

        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://xperta.test/api/v1/empresas/corp-real/ltds/estafeta/frecuencia/09800/57820'
                && $request->hasHeader('Corporativo', 'corp-real')
                && $request->hasHeader('x-api-key', 'secret-api-key')
                && $request->hasHeader('Content-Type', 'application/json')
                && $request->hasHeader('Accept', 'application/json')
                && $request->data() === ['token' => base64_encode('123|raw-token')];
        });
        $this->assertSame($before, DB::table('b2c_cotizaciones')->count());
    }

    public function test_unified_quote_uses_exact_postman_body_and_headers_once(): void
    {
        Cache::put($this->cacheKey(), '123|raw-token', 60);
        Http::fake(['*' => Http::response(['success' => true, 'data' => [[
            'costo' => 100, 'costo_ae' => 0, 'sub_total' => 100, 'total' => 116,
        ]]], 200)]);
        $before = DB::table('b2c_cotizaciones')->count();

        $response = app(XpertaEstafetaQuoteProvider::class)->quote(new UnifiedQuoteRequest(
            'estafeta', '09800', '57820', 5.0, 25.0, 25.0, 35.0, 'box', 'terrestre', 0.0
        ));

        $this->assertTrue($response->success);
        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://xperta.test/api/v1/empresas/corp-real/ltds/estafeta/servicios/terrestre/cotizaciones'
                && $request->hasHeader('Corporativo', 'corp-real')
                && $request->hasHeader('x-api-key', 'secret-api-key')
                && $request->hasHeader('Content-Type', 'application/json')
                && $request->hasHeader('Accept', 'application/json')
                && $request->data() === [
                    'token' => base64_encode('123|raw-token'), 'peso' => 5.0, 'largo' => 25.0,
                    'ancho' => 25.0, 'alto' => 35.0, 'cp' => '09800',
                    'cp_d' => '57820', 'valor_declarado' => 0.0,
                ];
        });
        $this->assertSame($before, DB::table('b2c_cotizaciones')->count());
        $this->assertFalse(config('services.shipping.unified_quote_enabled'));
        $this->assertFalse(config('services.shipping.prd_quote_probe_enabled'));
    }

    public function test_login_caches_raw_token_and_encodes_exactly_once(): void
    {
        $rawToken = '42|TOKEN-CONTENT';
        Http::fake(['*' => Http::response([
            'success' => true,
            'message' => ['token' => $rawToken],
        ], 200)]);

        $tokens = app(XpertaTokenService::class);

        $this->assertSame($rawToken, $tokens->token());
        $this->assertSame($rawToken, Cache::get($this->cacheKey()));
        $this->assertSame(base64_encode($rawToken), $tokens->encodedToken());
        $this->assertSame($rawToken, base64_decode($tokens->encodedToken(), true));
        Http::assertSentCount(1);
    }

    public function test_legacy_base64_cache_is_normalized_without_double_encoding(): void
    {
        $rawToken = '77|LEGACY';
        Cache::put($this->cacheKey(), base64_encode($rawToken), 60);

        $tokens = app(XpertaTokenService::class);

        $this->assertSame($rawToken, $tokens->token());
        $this->assertSame($rawToken, Cache::get($this->cacheKey()));
        $this->assertSame(base64_encode($rawToken), $tokens->encodedToken());
        Http::assertNothingSent();
    }

    public function test_guide_payload_uses_exact_base64_token(): void
    {
        Cache::put($this->cacheKey(), '88|GUIDE', 60);
        $quote = \Mockery::mock(B2cCotizacion::class)->makePartial();
        $quote->shouldReceive('hasCompleteShippingAddresses')->once()->andReturnTrue();
        $quote->shouldReceive('hasCompletePackageData')->once()->andReturnTrue();
        $quote->forceFill([
            'tipo_envio' => 'caja', 'medidas' => '25x25x35', 'peso' => 9,
            'ciudad_origen' => 'Mexico', 'estado_origen' => 'Ciudad de Mexico',
            'ciudad_destino' => 'Neza', 'estado_destino' => 'Mexico',
        ]);

        $payload = app(XpertaGuideService::class)->buildPayload($quote);

        $this->assertSame(base64_encode('88|GUIDE'), $payload['token']);
        $this->assertSame('88|GUIDE', base64_decode($payload['token'], true));
    }

    /** @dataProvider providerFailures */
    public function test_provider_failure_is_contained_without_http_500(string $failureType): void
    {
        $provider = \Mockery::mock(ZigoProviderRateService::class);
        $provider->shouldReceive('getOptionsForCotizacion')->once()
            ->andReturnUsing(function () use ($failureType): void {
                if ($failureType === 'timeout') {
                    throw new ConnectionException('timeout');
                }

                throw new \RuntimeException('XPERTA_HTTP_403');
            });
        app()->instance(ZigoProviderRateService::class, $provider);

        $method = new \ReflectionMethod(CotizacionPublicaController::class, 'getAvailableOptions');
        $method->setAccessible(true);
        $result = $method->invoke(app(CotizacionPublicaController::class), new B2cCotizacion());

        $this->assertSame([], $result);
        $this->assertSame(
            'No fue posible obtener tarifas de Estafeta en este momento. Intenta nuevamente.',
            session('rate_error')
        );
    }

    public static function providerFailures(): array
    {
        return [
            '403' => ['403'],
            'timeout' => ['timeout'],
        ];
    }

    private function cacheKey(): string
    {
        return 'xperta:token:' . hash('sha256', implode('|', [
            config('services.xperta.base_url'), config('services.xperta.corporativo'),
            config('services.xperta.email'),
        ]));
    }
}
