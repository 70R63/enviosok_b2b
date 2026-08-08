<?php

namespace Tests\Feature;

use App\Domain\Network\Channels\B2C\Models\TenantOperation;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Shipping\Local\LocalCoverageService;
use App\Domain\Shipping\Local\LocalGuideService;
use App\Domain\Shipping\Local\LocalQuoteService;
use App\Domain\Shipping\Local\LocalShipmentService;
use App\Domain\Shipping\Local\LocalTrackingService;
use App\Domain\Shipping\Local\Models\LocalShippingService;
use App\Domain\Shipping\Local\Models\LocalShippingZone;
use App\Services\ZigoPostalCodeService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

final class NetworkLocalShippingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            $this->markTestSkipped('Requires SQLite :memory:.');
        }
        $this->schema();
    }

    public function test_cp_64000_coverage_quote_limits_and_public_contract(): void
    {
        $this->mock(ZigoPostalCodeService::class, fn (MockInterface $m) => $m->shouldReceive('lookup')->with('64000')->andReturn(['success' => true]));
        $zone = LocalShippingZone::create(['code' => 'MTY', 'name' => 'Monterrey', 'status' => 'active']);
        app(LocalCoverageService::class)->assign($zone, '64000');
        LocalShippingService::create(['code' => 'SAME_DAY', 'name' => 'Same Day', 'origin_zone_id' => $zone->id, 'destination_zone_id' => $zone->id, 'service_level' => 'same_day', 'base_cost' => 80, 'base_price' => 149, 'currency' => 'MXN', 'max_weight' => 10, 'max_length' => 50, 'max_width' => 50, 'max_height' => 50, 'estimated_min_hours' => 4, 'estimated_max_hours' => 8, 'status' => 'active']);
        $quotes = app(LocalQuoteService::class);
        $result = $quotes->quote(['cp_origen' => '64000', 'cp_destino' => '64000', 'weight' => 2, 'length' => 20, 'width' => 10, 'height' => 10]);
        $this->assertSame('ZIGO_LOCAL', $result[0]['provider']);
        $this->assertSame(149.0, $result[0]['price']);
        $this->assertArrayNotHasKey('base_cost', $result[0]);
        $this->assertArrayNotHasKey('cost', $result[0]);
        $this->assertSame([], $quotes->quote(['cp_origen' => '64000', 'cp_destino' => '64000', 'weight' => 11, 'length' => 20, 'width' => 10, 'height' => 10]));
        $this->assertSame([], $quotes->quote(['cp_origen' => '64000', 'cp_destino' => '99999', 'weight' => 2]));
    }

    public function test_shipment_is_tenant_owned_snapshotted_idempotent_and_does_not_duplicate_usage(): void
    {
        [$tenant,$operation] = $this->operation();
        $data = ['sender' => ['name' => 'Origen', 'address' => 'Calle 1', 'postal_code' => '64000'], 'recipient' => ['name' => 'Destino', 'address' => 'Calle 2', 'postal_code' => '64000'], 'package' => ['type' => 'caja', 'weight' => 2, 'length' => 20, 'width' => 10, 'height' => 10], 'pricing' => ['final_price' => 149, 'currency' => 'MXN'], 'reference' => 'ABC'];
        $service = app(LocalShipmentService::class);
        $first = $service->create($tenant, $operation, $data);
        $second = $service->create($tenant, $operation, array_replace($data, ['reference' => 'CAMBIO']));
        $this->assertSame($first->id, $second->id);
        $this->assertMatchesRegularExpression('/^ZL\d{6}[A-Z0-9]{10}$/', $first->tracking_number);
        $this->assertSame('ABC', $first->guide_snapshot['reference']);
        $this->assertDatabaseCount('local_shipments', 1);
        $this->assertDatabaseCount('local_tracking_events', 1);
        $this->assertDatabaseCount('network_usage_events', 0);
        $this->assertTrue(collect(DB::select("PRAGMA index_list('local_shipments')"))->contains(fn ($index) => $index->name === 'local_ship_operation_uq' && (int) $index->unique === 1));
        try {
            $first->update(['sender_snapshot' => ['name' => 'Mutado']]);
            $this->fail('El snapshot debe ser inmutable.');
        } catch (\LogicException) {
            $this->assertSame('Origen', $first->fresh()->sender_snapshot['name']);
        }
    }

    public function test_shipment_guide_and_tracking_do_not_consume_confirm_usage(): void
    {
        [$tenant,$operation] = $this->operation();
        DB::table('network_usage_events')->insert(['created_at' => now(), 'updated_at' => now()]);
        $data = ['sender' => ['name' => 'Origen', 'address' => 'Calle 1', 'postal_code' => '64000'], 'recipient' => ['name' => 'Destino', 'address' => 'Calle 2', 'postal_code' => '64000'], 'package' => ['type' => 'sobre', 'weight' => 1], 'pricing' => ['final_price' => 120, 'currency' => 'MXN']];
        $shipment = app(LocalShipmentService::class)->create($tenant, $operation, $data);
        app(LocalShipmentService::class)->create($tenant, $operation, $data);
        app(LocalGuideService::class)->pdf($shipment, 'rapidgo.zigo.local');
        app(LocalTrackingService::class)->transition($shipment, 'READY_FOR_PICKUP');

        $this->assertDatabaseCount('network_usage_events', 1);
        $this->assertDatabaseCount('local_shipments', 1);
        $this->assertDatabaseCount('local_tracking_events', 2);
        $this->assertSame('https://rapidgo.zigo.local/tracking/'.$shipment->tracking_number, app(LocalGuideService::class)->trackingUrl($shipment, 'rapidgo.zigo.local'));
        $this->assertArrayNotHasKey('pricing', $shipment->guide_snapshot);
    }

    public function test_cross_tenant_is_hidden_and_tracking_is_append_only(): void
    {
        [$tenant,$operation] = $this->operation();
        $other = Tenant::create(['name' => 'Otro', 'slug' => 'otro', 'status' => 'active']);
        $this->expectException(NotFoundHttpException::class);
        app(LocalShipmentService::class)->create($other, $operation, ['sender' => [], 'recipient' => [], 'package' => [], 'pricing' => []]);
    }

    public function test_tracking_transition_and_guide_pdf_with_code128_and_qr(): void
    {
        [$tenant,$operation] = $this->operation();
        $shipment = app(LocalShipmentService::class)->create($tenant, $operation, ['sender' => ['name' => 'A', 'address' => 'Origen', 'postal_code' => '64000'], 'recipient' => ['name' => 'B', 'address' => 'Destino', 'postal_code' => '64000'], 'package' => ['type' => 'sobre', 'weight' => 1], 'pricing' => ['final_price' => 99]]);
        app(LocalTrackingService::class)->transition($shipment, 'READY_FOR_PICKUP');
        $this->assertSame('READY_FOR_PICKUP', $shipment->fresh()->status);
        $this->assertDatabaseCount('local_tracking_events', 2);
        $pdf = app(LocalGuideService::class)->pdf($shipment->fresh(), 'cliente.zigo.local');
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(2000, strlen($pdf));
    }

    private function operation(): array
    {
        $tenant = Tenant::create(['name' => 'RapidGo', 'slug' => 'rapidgo', 'status' => 'active']);
        $tenant->branding()->create(['brand_name' => 'RapidGo Local', 'primary_color' => '#123456']);
        $operation = TenantOperation::create(['tenant_id' => $tenant->id, 'channel' => 'b2c', 'status' => 'confirmed', 'provider' => 'ZIGO_LOCAL', 'service_code' => 'SAME_DAY', 'metadata' => []]);

        return [$tenant, $operation];
    }

    private function schema(): void
    {
        Schema::create('users', fn (Blueprint $t) => $t->id());
        Schema::create('network_tenants', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->nullable();
            $t->string('name');
            $t->string('slug');
            $t->string('status');
            $t->unsignedBigInteger('current_plan_id')->nullable();
            $t->timestamps();
        });
        Schema::create('network_tenant_brandings', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->string('brand_name')->nullable();
            $t->string('logo_path')->nullable();
            $t->string('primary_color')->nullable();
            $t->string('secondary_color')->nullable();
            $t->string('accent_color')->nullable();
            $t->string('favicon_path')->nullable();
            $t->string('support_email')->nullable();
            $t->string('support_phone')->nullable();
            $t->timestamps();
        });
        Schema::create('network_tenant_operations', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->nullable();
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('subscription_id')->nullable();
            $t->string('channel');
            $t->string('status');
            $t->string('source_type')->nullable();
            $t->unsignedBigInteger('source_id')->nullable();
            $t->string('provider')->nullable();
            $t->string('service_code')->nullable();
            $t->string('external_reference')->nullable();
            $t->unsignedBigInteger('created_by_user_id')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamps();
        });
        Schema::create('network_usage_events', function (Blueprint $t) {
            $t->id();
            $t->timestamps();
        });
        $migration = require database_path('migrations/2026_08_09_100000_create_local_shipping_foundation_tables.php');
        $migration->up();
    }
}
