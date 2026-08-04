<?php

namespace Tests\Feature;

use App\Services\Shipping\Data\UnifiedQuoteRequest;
use App\Services\Shipping\Providers\XpertaEstafetaQuoteProvider;
use App\Services\Shipping\Xperta\XpertaFrequencyService;
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
        Cache::put($this->cacheKey(), 'raw-token', 60);
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
                && $request->data() === ['token' => 'raw-token'];
        });
        $this->assertSame($before, DB::table('b2c_cotizaciones')->count());
    }

    public function test_unified_quote_uses_exact_postman_body_and_headers_once(): void
    {
        Cache::put($this->cacheKey(), 'raw-token', 60);
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
                    'token' => 'raw-token', 'peso' => 5.0, 'largo' => 25.0,
                    'ancho' => 25.0, 'alto' => 35.0, 'cp' => '09800',
                    'cp_d' => '57820', 'valor_declarado' => 0.0,
                ];
        });
        $this->assertSame($before, DB::table('b2c_cotizaciones')->count());
        $this->assertFalse(config('services.shipping.unified_quote_enabled'));
        $this->assertFalse(config('services.shipping.prd_quote_probe_enabled'));
    }

    private function cacheKey(): string
    {
        return 'xperta:token:' . hash('sha256', implode('|', [
            config('services.xperta.base_url'), config('services.xperta.corporativo'),
            config('services.xperta.email'),
        ]));
    }
}
