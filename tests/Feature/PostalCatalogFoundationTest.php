<?php

namespace Tests\Feature;

use App\Domain\Shipping\Local\LocalCoverageService;
use App\Domain\Shipping\Local\Models\LocalShippingZone;
use App\Http\Controllers\API\CPController;
use App\Http\Controllers\API\Hub\PostalCodeController;
use App\Http\Controllers\Web\PostalCodeLookupController;
use App\Services\PostalCodeCatalogImporter;
use App\Services\ZigoPostalCodeService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class PostalCatalogFoundationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            $this->markTestSkipped('Requires SQLite :memory:.');
        }
        $this->schema();
    }

    public function test_sepomex_mapping_multiple_settlements_and_idempotence(): void
    {
        $rows = [
            ['d_codigo' => '64000', 'd_estado' => 'Nuevo León', 'd_mnpio' => 'Monterrey', 'd_ciudad' => 'Monterrey', 'd_asenta' => 'Monterrey Centro', 'd_tipo_asenta' => 'Colonia', 'd_zona' => 'Urbano'],
            ['d_codigo' => '64000', 'd_estado' => 'Nuevo León', 'd_mnpio' => 'Monterrey', 'd_ciudad' => 'Monterrey', 'd_asenta' => 'Obispado', 'd_tipo_asenta' => 'Colonia', 'd_zona' => 'Urbano'],
        ];
        $importer = app(PostalCodeCatalogImporter::class);
        $first = $importer->import($rows, 1);
        $second = $importer->import($rows, 1);

        $this->assertSame(2, $first['inserted']);
        $this->assertSame(2, $second['updated']);
        $this->assertDatabaseCount('zigo_postal_codes', 2);
        $this->assertSame('Nuevo León', DB::table('zigo_postal_codes')->first()->estado);
    }

    public function test_64000_service_web_lookup_colonias_and_local_assignment_share_catalog(): void
    {
        app(PostalCodeCatalogImporter::class)->import([
            ['d_codigo' => '64000', 'd_estado' => 'Nuevo León', 'd_mnpio' => 'Monterrey', 'd_ciudad' => 'Monterrey', 'd_asenta' => 'Centro', 'd_tipo_asenta' => 'Colonia', 'd_zona' => 'Urbano'],
            ['d_codigo' => '64000', 'd_estado' => 'Nuevo León', 'd_mnpio' => 'Monterrey', 'd_ciudad' => 'Monterrey', 'd_asenta' => 'Obispado', 'd_tipo_asenta' => 'Colonia', 'd_zona' => 'Urbano'],
        ]);

        $service = app(ZigoPostalCodeService::class);
        $this->assertSame(2, $service->lookup('64000')['meta']['total_colonias']);
        $controller = app(PostalCodeLookupController::class);
        $this->assertSame(200, $controller->show('64000', $service)->status());
        $colonias = $controller->colonias(Request::create('/b2c/cp/colonias', 'GET', ['cp' => '64000']), $service);
        $this->assertCount(2, $colonias->getData(true)['data']);
        $this->assertSame(200, app(PostalCodeController::class)->show('64000', $service)->status());
        $legacyB2b = app(CPController::class)
            ->colonias(Request::create('/api/cp/colonias', 'GET', ['cp' => '64000']), $service);
        $this->assertSame(200, $legacyB2b->status());

        $zone = LocalShippingZone::create(['code' => 'MTY', 'name' => 'Monterrey', 'status' => 'active']);
        app(LocalCoverageService::class)->assign($zone, '64000');
        $this->assertSame($zone->id, app(LocalCoverageService::class)->zoneFor('64000')->id);
    }

    public function test_empty_catalog_rejects_lookup_and_local_assignment_without_legacy_fallback(): void
    {
        $this->assertSame(404, app(ZigoPostalCodeService::class)->lookup('64000')['status']);
        $zone = LocalShippingZone::create(['code' => 'MTY', 'name' => 'Monterrey', 'status' => 'active']);
        $this->expectException(ValidationException::class);
        app(LocalCoverageService::class)->assign($zone, '64000');
    }

    public function test_invalid_mapping_is_reported_without_mutating_catalog(): void
    {
        $stats = app(PostalCodeCatalogImporter::class)->import([['d_codigo' => '6400', 'd_asenta' => 'Centro']]);
        $this->assertSame(1, $stats['errors']);
        $this->assertDatabaseCount('zigo_postal_codes', 0);
    }

    public function test_b2c_postal_routes_do_not_leak_to_api_host(): void
    {
        config([
            'zigo_domains.routing_enabled' => true,
            'zigo_domains.portals.b2c.host' => 'b2c.test',
            'zigo_domains.portals.api.host' => 'api.test',
        ]);

        $this->withServerVariables(['HTTP_HOST' => 'api.test'])
            ->get('/postal-code/lookup/64000')
            ->assertNotFound();
        $this->withServerVariables(['HTTP_HOST' => 'api.test'])
            ->get('/b2c/cp/colonias?cp=64000')
            ->assertNotFound();
    }

    public function test_file_bootstrap_command_is_idempotent(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'zigo-postal-');
        file_put_contents($path, implode("\n", [
            'd_codigo,d_estado,d_mnpio,d_ciudad,d_asenta,d_tipo_asenta,d_zona',
            '64000,Nuevo León,Monterrey,Monterrey,Centro,Colonia,Urbano',
            '64000,Nuevo León,Monterrey,Monterrey,Obispado,Colonia,Urbano',
        ]));

        try {
            $this->assertSame(0, Artisan::call('zigo:postal-codes:sync', ['--from' => 'file', '--file' => $path, '--batch' => 1]));
            $this->assertSame(0, Artisan::call('zigo:postal-codes:sync', ['--from' => 'file', '--file' => $path, '--batch' => 1]));
            $this->assertDatabaseCount('zigo_postal_codes', 2);
        } finally {
            @unlink($path);
        }
    }

    private function schema(): void
    {
        Schema::create('zigo_postal_codes', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_postal')->index();
            $table->string('estado');
            $table->string('municipio');
            $table->string('ciudad')->nullable();
            $table->string('asentamiento');
            $table->string('tipo_asentamiento')->nullable();
            $table->string('zona')->nullable();
            $table->boolean('cobertura_estafeta')->default(true);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        Schema::create('local_shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->string('status');
            $table->timestamps();
        });
        Schema::create('local_shipping_zone_postal_codes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('zone_id');
            $table->string('postal_code');
            $table->timestamps();
            $table->unique(['zone_id', 'postal_code']);
        });
    }
}
