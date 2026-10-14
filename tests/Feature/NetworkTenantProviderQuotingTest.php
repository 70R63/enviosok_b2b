<?php

namespace Tests\Feature;

use App\Domain\Network\Providers\LocalProviderCommercialPricing;
use App\Domain\Network\Providers\LocalProviderQuoteEvaluator;
use App\Domain\Network\Providers\Models\ProviderConnection;
use App\Domain\Network\Providers\TenantProviderQuoteSource;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Network\Tenancy\Models\TenantDomain;
use App\Domain\Network\Tenancy\TenantContext;
use App\Domain\Shipping\Local\Models\LocalShippingPackageRule;
use App\Domain\Shipping\Local\Models\LocalShippingPricingRule;
use App\Domain\Shipping\Local\Models\LocalShippingService;
use App\Domain\Shipping\Local\Models\LocalShippingZone;
use App\Domain\Shipping\Local\Models\LocalShippingZonePostalCode;
use App\Http\Controllers\B2C\CotizacionPublicaController;
use App\Models\B2cCotizacion;
use App\Models\ZigoPricingRule;
use App\Services\B2cQuoteOptionsService;
use App\Services\ZigoProviderRateService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class NetworkTenantProviderQuotingTest extends TestCase
{
    private Tenant $consumer;

    private Tenant $provider;

    private LocalShippingService $service;

    private ProviderConnection $connection;

    private B2cCotizacion $quote;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            $this->markTestSkipped('Requires isolated SQLite :memory:.');
        }
        Schema::create('network_tenants', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('status');
            $t->timestamps();
        });
        Schema::create('network_tenant_domains', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id');
            $t->string('domain');
            $t->string('status');
            $t->timestamps();
        });
        Schema::create('network_tenant_operations', fn (Blueprint $t) => $t->id());
        Schema::create('users', fn (Blueprint $t) => $t->id());
        foreach (['2026_08_09_100000_create_local_shipping_foundation_tables.php',
            '2026_08_20_100000_extend_local_shipping_for_tenant_logistics.php',
            '2026_10_13_100000_create_network_provider_connections.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        require_once database_path('migrations/2026_07_11_175856_create_zigo_pricing_rules_table.php');
        (new \CreateZigoPricingRulesTable)->up();
        $this->consumer = $this->tenant('consumer');
        [$this->provider, $this->service, $this->connection] = $this->providerFixture('provider');
        app(TenantContext::class)->set($this->consumer);
        $this->quote = new B2cCotizacion(['cp_origen' => '64000', 'cp_destino' => '64001',
            'tipo_envio' => 'caja', 'peso' => 2, 'medidas' => '20x10x10', 'referencia' => 'LANDING_PUBLICA']);
        $this->quote->id = 99;
    }

    public function test_consumer_without_connection_has_no_local_options(): void
    {
        app(TenantContext::class)->set($this->tenant('unconnected'));
        $this->assertSame([], $this->localOptions());
    }

    public function test_inactive_connection_has_no_local_options(): void
    {
        $this->connection->forceFill(['status' => 'inactive'])->save();
        $this->assertSame([], $this->localOptions());
    }

    public function test_expired_connection_has_no_local_options(): void
    {
        $this->connection->forceFill(['valid_to' => now()->subMinute()])->save();
        $this->assertSame([], $this->localOptions());
    }

    public function test_service_not_authorized_is_hidden(): void
    {
        DB::table('network_provider_connection_services')->delete();
        $this->assertSame([], $this->localOptions());
    }

    public function test_unpublished_service_is_hidden(): void
    {
        $this->service->update(['published' => false]);
        $this->assertSame([], $this->localOptions());
    }

    public function test_both_postal_codes_covered_return_option(): void
    {
        $option = $this->localOptions()[0];
        $this->assertSame('tenant_provider_local', $option['provider_source']);
        $this->assertSame('LOCAL_SHIPPING', $option['capability']);
        $this->assertSame($this->provider->id, $option['_tenant_provider']['provider_tenant_id']);
    }

    public function test_only_origin_covered_is_hidden(): void
    {
        LocalShippingZonePostalCode::where('postal_code', '64001')->delete();
        $this->assertSame([], $this->localOptions());
    }

    public function test_only_destination_covered_is_hidden(): void
    {
        LocalShippingZonePostalCode::where('postal_code', '64000')->delete();
        $this->assertSame([], $this->localOptions());
    }

    public function test_neither_postal_code_covered_is_hidden(): void
    {
        LocalShippingZonePostalCode::query()->delete();
        $this->assertSame([], $this->localOptions());
    }

    public function test_package_outside_weight_and_dimensions_is_hidden(): void
    {
        $this->quote->peso = 11;
        $this->assertSame([], $this->localOptions());
        $this->quote->peso = 2;
        $this->quote->medidas = '100x10x10';
        $this->assertSame([], $this->localOptions());
    }

    public function test_missing_tariff_is_hidden(): void
    {
        LocalShippingPricingRule::query()->delete();
        $this->assertSame([], $this->localOptions());
    }

    public function test_expired_inactive_and_wrong_package_tariffs_are_hidden(): void
    {
        LocalShippingPricingRule::query()->update(['valid_to' => now()->subMinute()]);
        $this->assertSame([], $this->localOptions());
        LocalShippingPricingRule::query()->update(['valid_to' => null, 'active' => false]);
        $this->assertSame([], $this->localOptions());
        LocalShippingPricingRule::query()->update(['active' => true, 'package_type' => 'sobre']);
        $this->assertSame([], $this->localOptions());
    }

    public function test_service_specific_limits_and_missing_package_rules_fail_closed(): void
    {
        $this->service->update(['max_weight' => 1]);
        $this->assertSame([], $this->localOptions());
        $this->service->update(['max_weight' => null, 'max_width' => 5]);
        $this->assertSame([], $this->localOptions());
        $this->service->update(['max_width' => null]);
        LocalShippingPackageRule::query()->update(['active' => false]);
        $this->assertSame([], $this->localOptions());
    }

    public function test_package_flat_tariff_is_reused_without_snapshots(): void
    {
        $this->service->update(['pricing_strategy' => 'PACKAGE_FLAT']);
        LocalShippingPricingRule::query()->update(['package_type' => 'caja', 'amount' => 80]);
        $this->assertSame(101.0, $this->localOptions()[0]['precio']);
        $this->assertDatabaseCount('local_shipping_quote_snapshots', 0);
    }

    public function test_future_connection_and_wrong_capability_are_hidden(): void
    {
        $this->connection->forceFill(['valid_from' => now()->addDay()])->save();
        $this->assertSame([], $this->localOptions());
        $this->connection->forceFill(['valid_from' => null])->save();
        DB::table('network_provider_connection_services')->delete();
        $this->connection->forceFill(['capability' => 'OTHER'])->save();
        $this->assertSame([], $this->localOptions());
    }

    public function test_unknown_or_unverified_host_does_not_resolve_consumer(): void
    {
        app(TenantContext::class)->clear();
        request()->merge(['tenant_id' => $this->consumer->id]);
        $this->assertSame([], $this->localOptions());
        TenantDomain::create(['tenant_id' => $this->consumer->id, 'domain' => request()->getHost(), 'status' => 'pending']);
        $this->assertSame([], $this->localOptions());
    }

    public function test_valid_tariff_separates_provider_cost_from_customer_price(): void
    {
        $option = $this->localOptions()[0];
        $this->assertSame(100.0, $option['base_price']);
        $this->assertSame(125.0, $option['precio']);
        $this->assertSame('MXN', $option['currency']);
        $this->assertSame('local_shipping_pricing_rules', $option['rate_source']);
    }

    public function test_two_local_providers_return_options(): void
    {
        $this->providerFixture('second');
        $this->assertCount(2, $this->localOptions());
    }

    public function test_foreign_tenant_coverage_package_and_tariff_never_mix(): void
    {
        [$other] = $this->providerFixture('other');
        LocalShippingZonePostalCode::where('tenant_id', $this->provider->id)->update(['tenant_id' => $other->id]);
        $this->assertCount(1, $this->localOptions());
        LocalShippingZonePostalCode::where('zone_id', $this->service->origin_zone_id)->update(['tenant_id' => $this->provider->id]);
        LocalShippingZonePostalCode::where('zone_id', $this->service->destination_zone_id)->update(['tenant_id' => $this->provider->id]);
        LocalShippingPackageRule::where('service_id', $this->service->id)->update(['tenant_id' => $other->id]);
        $this->assertCount(1, $this->localOptions());
        LocalShippingPackageRule::where('service_id', $this->service->id)->update(['tenant_id' => $this->provider->id]);
        LocalShippingPricingRule::where('service_id', $this->service->id)->update(['tenant_id' => $other->id]);
        $options = $this->localOptions();
        $this->assertCount(1, $options);
        $this->assertSame($other->id, $options[0]['_tenant_provider']['provider_tenant_id']);
    }

    public function test_same_service_code_is_distinct_by_provider_identity(): void
    {
        [$other, $service] = $this->providerFixture('second');
        LocalShippingPricingRule::where('service_id', $service->id)->update(['amount' => 200]);
        $options = $this->localOptions();
        $this->assertSame($options[0]['service_code'], $options[1]['service_code']);
        $this->assertNotSame($options[0]['option_key'], $options[1]['option_key']);
        $this->assertNotSame($options[0]['carrier'], $options[1]['carrier']);
        $this->assertSame(200.0, $options[1]['provider_cost']);
        $this->assertSame($other->id, $options[1]['_tenant_provider']['provider_tenant_id']);
    }

    public function test_local_failure_preserves_national_options(): void
    {
        $this->mock(TenantProviderQuoteSource::class)->shouldReceive('options')->andThrow(new \RuntimeException('sensitive details'));
        $this->nationalMock([['logistico' => 'Estafeta', 'base_price' => 50]]);
        $this->assertSame([['logistico' => 'Estafeta', 'base_price' => 50]], $this->aggregate());
    }

    public function test_national_failure_preserves_local_options(): void
    {
        $this->mock(ZigoProviderRateService::class)->shouldReceive('getOptionsForCotizacion')->andThrow(new \RuntimeException('network'));
        $this->assertCount(1, $this->aggregate());
        $this->assertSame('tenant_provider_local', $this->aggregate()[0]['provider_source']);
        $this->assertTrue(session()->has('rate_error'));
    }

    public function test_national_options_are_unchanged_without_connections(): void
    {
        $this->connection->forceFill(['status' => 'inactive'])->save();
        $national = [['logistico' => 'Estafeta', 'provider_source' => 'xperta', 'service_code' => 'ground', 'base_price' => 123.45]];
        $this->nationalMock($national);
        $this->assertSame($national, $this->aggregate());
    }

    public function test_uncovered_origin_or_destination_preserves_national_options(): void
    {
        $national = [['logistico' => 'Estafeta', 'provider_source' => 'xperta', 'service_code' => 'ground', 'base_price' => 123.45]];
        $this->nationalMock($national);
        foreach ([['99999', '64001'], ['64000', '99999']] as [$origin, $destination]) {
            $this->quote->cp_origen = $origin;
            $this->quote->cp_destino = $destination;
            $this->assertSame($national, $this->aggregate());
        }
    }
    public function test_quote_creates_no_operation_shipment_snapshot_tracking_or_any_write(): void
    {
        DB::enableQueryLog();
        $this->assertCount(1, $this->localOptions());
        $sql = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();
        $this->assertFalse(collect($sql)->contains(fn ($s) => preg_match('/^\s*(insert|update|delete|replace)/i', $s) === 1));
        foreach (['network_tenant_operations', 'local_shipments', 'local_tracking_events', 'local_shipping_quote_snapshots'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_quote_never_modifies_tenant_context(): void
    {
        $this->localOptions();
        $this->assertSame($this->consumer, app(TenantContext::class)->current());
    }

    public function test_estafeta_rule_cannot_price_a_local_provider(): void
    {
        ZigoPricingRule::query()->delete();
        ZigoPricingRule::create(['name' => 'National', 'carrier' => 'ESTAFETA', 'customer_segment' => 'anonymous',
            'package_type' => 'all', 'margin_percentage' => 50, 'active' => true]);
        $this->assertSame([], $this->localOptions());
    }

    public function test_missing_commercial_configuration_hides_provider(): void
    {
        ZigoPricingRule::query()->delete();
        $this->assertSame([], $this->localOptions());
    }

    public function test_one_failed_provider_does_not_hide_other_providers(): void
    {
        [$other] = $this->providerFixture('second');
        $real = app(LocalProviderQuoteEvaluator::class);
        $mock = $this->mock(LocalProviderQuoteEvaluator::class);
        $mock->shouldReceive('options')->withArgs(fn ($c, $p) => $p->id === $this->provider->id)->andThrow(new \RuntimeException('bad provider'));
        $mock->shouldReceive('options')->withArgs(fn ($c, $p) => $p->id === $other->id)->andReturnUsing(fn (...$args) => $real->options(...$args));
        $this->assertSame($other->id, $this->localOptions()[0]['_tenant_provider']['provider_tenant_id']);
    }

    public function test_provider_and_consumer_status_service_validity_and_postal_validity(): void
    {
        $this->provider->update(['status' => 'suspended']);
        $this->assertSame([], $this->localOptions());
        $this->provider->update(['status' => 'active']);
        $this->service->update(['valid_from' => now()->addDay()]);
        $this->assertSame([], $this->localOptions());
        $this->service->update(['valid_from' => null]);
        LocalShippingZonePostalCode::query()->update(['valid_to' => now()->subDay()]);
        $this->assertSame([], $this->localOptions());
    }

    public function test_commercial_rules_are_consumer_specific(): void
    {
        $other = $this->tenant('other-consumer');
        $c = ProviderConnection::forceCreate(['consumer_tenant_id' => $other->id, 'provider_tenant_id' => $this->provider->id,
            'capability' => 'LOCAL_SHIPPING', 'status' => 'active']);
        $this->grant($c, $this->service);
        app(TenantContext::class)->set($other);
        $this->assertSame([], $this->localOptions());
    }

    public function test_verified_host_resolves_consumer_without_context_mutation_or_browser_ids(): void
    {
        app(TenantContext::class)->clear();
        TenantDomain::create(['tenant_id' => $this->consumer->id, 'domain' => request()->getHost(), 'status' => 'verified']);
        request()->merge(['tenant_id' => $this->provider->id, 'consumer_tenant_id' => $this->provider->id]);
        $this->assertCount(1, $this->localOptions());
        $this->assertNull(app(TenantContext::class)->current());
    }

    public function test_distance_tariff_and_insurance_are_not_invented(): void
    {
        $this->service->update(['pricing_strategy' => 'BASE_PLUS_OVERAGE']);
        $this->assertSame([], $this->localOptions());
        $this->service->update(['pricing_strategy' => 'FLAT']);
        $this->quote->requiere_seguro_envio = true;
        $this->assertSame([], $this->localOptions());
    }

    public function test_public_view_shows_preview_without_cost_ids_or_checkout_and_selection_is_rejected(): void
    {
        $this->nationalMock([]);
        $controller = app(CotizacionPublicaController::class);
        $options = $controller->getPublicOptionsForLanding($this->quote);
        $html = view('index', ['cotizacion_publica' => $this->quote, 'cotizacion_id' => 99, 'opciones' => $options])->render();
        $this->assertStringContainsString('Sólo cotización', $html);
        $this->assertStringContainsString('disabled', $html);
        $this->assertStringContainsString('$125.00', $html);
        $this->assertStringNotContainsString('$100.00', $html);
        $this->assertStringNotContainsString('provider_tenant_id', $html);
        $method = new \ReflectionMethod($controller, 'findSelectedOption');
        $this->assertNull($method->invoke($controller, $this->quote, $this->provider->name, $this->service->name));
    }

    private function localOptions(): array
    {
        return app(TenantProviderQuoteSource::class)->options($this->quote, 'anonymous');
    }

    private function aggregate(): array
    {
        return app(B2cQuoteOptionsService::class)->options($this->quote, fn ($o) => $o, 'anonymous');
    }

    private function nationalMock(array $options): void
    {
        $this->mock(ZigoProviderRateService::class)->shouldReceive('getOptionsForCotizacion')->andReturn($options);
    }

    private function tenant(string $name): Tenant
    {
        return Tenant::create(['name' => $name, 'slug' => $name, 'status' => 'active']);
    }

    private function grant(ProviderConnection $c, LocalShippingService $s): void
    {
        DB::table('network_provider_connection_services')->insert(['connection_id' => $c->id,
            'provider_tenant_id' => $c->provider_tenant_id, 'capability' => 'LOCAL_SHIPPING', 'local_shipping_service_id' => $s->id]);
    }

    private function providerFixture(string $name): array
    {
        $provider = $this->tenant($name);
        $origin = LocalShippingZone::create(['tenant_id' => $provider->id, 'code' => 'ORIGIN', 'name' => 'Origin', 'status' => 'active']);
        $destination = LocalShippingZone::create(['tenant_id' => $provider->id, 'code' => 'DEST', 'name' => 'Dest', 'status' => 'active']);
        foreach ([[$origin, '64000'], [$destination, '64001']] as [$zone, $cp]) {
            LocalShippingZonePostalCode::create(['tenant_id' => $provider->id, 'zone_id' => $zone->id, 'postal_code' => $cp, 'active' => true]);
        }
        $service = LocalShippingService::create(['tenant_id' => $provider->id, 'code' => 'SAME', 'name' => 'Local',
            'origin_zone_id' => $origin->id, 'destination_zone_id' => $destination->id, 'service_level' => 'same_day',
            'base_cost' => 999, 'base_price' => 999, 'currency' => 'MXN', 'status' => 'active', 'published' => true,
            'pricing_strategy' => 'FLAT', 'sla_text' => '24 h']);
        LocalShippingPackageRule::create(['tenant_id' => $provider->id, 'service_id' => $service->id, 'package_type' => 'caja',
            'max_weight_kg' => 10, 'max_dimension_1_cm' => 50, 'max_dimension_2_cm' => 50, 'max_dimension_3_cm' => 50, 'active' => true]);
        LocalShippingPricingRule::create(['tenant_id' => $provider->id, 'service_id' => $service->id, 'amount' => 100, 'active' => true]);
        $connection = ProviderConnection::forceCreate(['consumer_tenant_id' => $this->consumer->id, 'provider_tenant_id' => $provider->id,
            'capability' => 'LOCAL_SHIPPING', 'status' => 'active']);
        $this->grant($connection, $service);
        ZigoPricingRule::create(['name' => 'Explicit local', 'carrier' => app(LocalProviderCommercialPricing::class)->carrierKey($this->consumer, $provider),
            'customer_segment' => 'anonymous', 'package_type' => 'all', 'margin_percentage' => 20, 'fixed_fee' => 5, 'active' => true]);

        return [$provider, $service, $connection];
    }
}
