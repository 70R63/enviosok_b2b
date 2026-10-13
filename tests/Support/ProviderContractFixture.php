<?php

namespace Tests\Support;

use App\Domain\Network\Providers\LocalProviderCommercialPricing;
use App\Domain\Network\Providers\Models\ProviderConnection;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Shipping\Local\Models\LocalShippingPackageRule;
use App\Domain\Shipping\Local\Models\LocalShippingPricingRule;
use App\Domain\Shipping\Local\Models\LocalShippingService;
use App\Domain\Shipping\Local\Models\LocalShippingZone;
use App\Domain\Shipping\Local\Models\LocalShippingZonePostalCode;
use App\Models\B2cCotizacion;
use App\Models\ZigoPricingRule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Isolated test schemas only, never a provisioning tool. */
final class ProviderContractFixture
{
    public static function schema(): void
    {
        Schema::create('network_tenants', function (Blueprint $t) {
            $t->engine = 'InnoDB';
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
        Schema::create('local_driver_assignments', fn (Blueprint $t) => $t->id());
        Schema::create('users', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password');
            $t->unsignedBigInteger('empresa_id')->nullable();
            $t->rememberToken();
            $t->timestamps();
        });
        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('slug');
            $t->timestamps();
        });
        Schema::create('users_roles', function (Blueprint $t) {
            $t->foreignId('user_id');
            $t->foreignId('roles_id');
        });
        Schema::create('sepomex', function (Blueprint $t) {
            $t->string('d_codigo');
            $t->string('d_asenta');
        });
        Schema::create('b2c_saldos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id');
            $t->decimal('saldo', 12, 2)->default(0);
            $t->timestamps();
        });
        Schema::create('b2c_direcciones', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id');
            $t->boolean('activo')->default(true);
            $t->string('tipo');
            $t->string('cp');
            $t->boolean('favorita')->default(false);
            $t->boolean('principal')->default(false);
            $t->timestamps();
        });
        Schema::create('b2c_cotizaciones', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->id();
            $model = new B2cCotizacion;
            $casts = $model->getCasts();
            foreach ($model->getFillable() as $field) {
                $cast = $casts[$field] ?? '';
                if ($field === 'user_id') {
                    $t->unsignedBigInteger($field)->nullable();
                } elseif (str_starts_with($cast, 'decimal')) {
                    $t->decimal($field, 12, 2)->nullable();
                } elseif ($cast === 'boolean') {
                    $t->boolean($field)->nullable();
                } elseif ($cast === 'datetime') {
                    $t->timestamp($field)->nullable();
                } elseif ($cast === 'array') {
                    $t->json($field)->nullable();
                } elseif ($cast === 'integer') {
                    $t->integer($field)->nullable();
                } else {
                    $t->string($field)->nullable();
                }
            }
            $t->timestamps();
        });
        require_once database_path('migrations/2026_07_11_175856_create_zigo_pricing_rules_table.php');
        (new \CreateZigoPricingRulesTable)->up();
        foreach (['2026_08_09_100000_create_local_shipping_foundation_tables.php',
            '2026_08_20_100000_extend_local_shipping_for_tenant_logistics.php',
            '2026_10_13_100000_create_network_provider_connections.php',
            '2026_10_13_110000_create_network_provider_contract_snapshots.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
    }

    public static function seed(): array
    {
        $consumer = Tenant::create(['name' => 'Consumer', 'slug' => 'consumer', 'status' => 'active']);
        $provider = Tenant::create(['name' => 'Provider Express', 'slug' => 'provider', 'status' => 'active']);
        $connection = ProviderConnection::forceCreate(['consumer_tenant_id' => $consumer->id,
            'provider_tenant_id' => $provider->id, 'capability' => 'LOCAL_SHIPPING', 'status' => 'active']);
        $zones = [];
        foreach (['64000', '64001'] as $cp) {
            $zone = LocalShippingZone::create(['tenant_id' => $provider->id, 'code' => 'ZONE_'.$cp, 'name' => $cp, 'status' => 'active']);
            LocalShippingZonePostalCode::create(['tenant_id' => $provider->id, 'zone_id' => $zone->id, 'postal_code' => $cp, 'active' => true]);
            $zones[] = $zone;
        }
        $service = LocalShippingService::create(['tenant_id' => $provider->id, 'code' => 'LOCAL', 'name' => 'Envío local',
            'origin_zone_id' => $zones[0]->id, 'destination_zone_id' => $zones[1]->id, 'service_level' => 'same_day',
            'base_cost' => 900, 'base_price' => 900, 'currency' => 'MXN', 'status' => 'active', 'published' => true, 'pricing_strategy' => 'FLAT', 'sla_text' => '24 h']);
        LocalShippingPackageRule::create(['tenant_id' => $provider->id, 'service_id' => $service->id, 'package_type' => 'caja',
            'max_weight_kg' => 10, 'max_dimension_1_cm' => 50, 'max_dimension_2_cm' => 50, 'max_dimension_3_cm' => 50, 'active' => true]);
        $tariff = LocalShippingPricingRule::create(['tenant_id' => $provider->id, 'service_id' => $service->id, 'amount' => 100, 'active' => true]);
        DB::table('network_provider_connection_services')->insert(['connection_id' => $connection->id,
            'provider_tenant_id' => $provider->id, 'capability' => 'LOCAL_SHIPPING', 'local_shipping_service_id' => $service->id]);
        $commercial = ZigoPricingRule::create(['name' => 'Consumer commercial rule',
            'carrier' => app(LocalProviderCommercialPricing::class)->carrierKey($consumer, $provider),
            'customer_segment' => 'anonymous', 'package_type' => 'all', 'margin_percentage' => 20, 'fixed_fee' => 5, 'active' => true]);
        $quote = B2cCotizacion::create(['cp_origen' => '64000', 'cp_destino' => '64001', 'colonia_origen' => 'Origin',
            'colonia_destino' => 'Destination', 'tipo_envio' => 'caja', 'peso' => 2, 'medidas' => '20x10x10',
            'referencia' => 'LANDING_PUBLICA', 'estatus' => 'COTIZADA', 'requiere_seguro_envio' => false, 'seguro_monto' => 0]);

        return compact('consumer', 'provider', 'connection', 'service', 'tariff', 'commercial', 'quote');
    }
}
