<?php

namespace Tests\Feature;

use App\Domain\Network\Providers\Models\ProviderConnection;
use App\Domain\Network\Providers\ProviderConnectionService;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Network\Tenancy\TenantContext;
use App\Domain\Shipping\Local\Models\LocalShippingService;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class NetworkProviderConnectionTest extends TestCase
{
    private Tenant $consumer;
    private Tenant $provider;
    private ProviderConnectionService $service;
    private object $migration;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            $this->markTestSkipped('Requires isolated SQLite :memory:.');
        }
        DB::statement('PRAGMA foreign_keys = ON');
        Schema::create('network_tenants', function (Blueprint $t): void {
            $t->id(); $t->uuid('uuid')->unique(); $t->string('name');
            $t->string('slug')->unique(); $t->string('status'); $t->timestamps();
        });
        Schema::create('local_shipping_services', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('tenant_id')->nullable();
            $t->string('code'); $t->string('name'); $t->string('status');
            $t->boolean('published')->default(false);
            $t->timestamp('valid_from')->nullable(); $t->timestamp('valid_to')->nullable(); $t->timestamps();
        });
        $this->migration = require database_path('migrations/2026_10_13_100000_create_network_provider_connections.php');
        $this->migration->up();
        $this->consumer = $this->tenant('consumer');
        $this->provider = $this->tenant('provider');
        $this->service = app(ProviderConnectionService::class);
        $this->travelTo(now()->startOfSecond());
    }

    public function test_valid_connection_and_current_consumer_context(): void
    {
        $connection = $this->connect(['valid_from' => now(), 'valid_to' => now()->addDay()]);
        app(TenantContext::class)->set($this->consumer);
        $this->assertTrue($this->allowed());
        $this->assertSame([$connection->id], $this->service->currentConnections('LOCAL_SHIPPING')->modelKeys());
        $this->assertSame($this->consumer, app(TenantContext::class)->current());
    }

    public function test_self_connection_is_rejected_by_model(): void
    {
        $this->expectException(ValidationException::class);
        $this->connect(['provider_tenant_id' => $this->consumer->id]);
    }

    public function test_inactive_connection_is_unavailable(): void
    {
        $this->connect(['status' => 'inactive']);
        $this->assertFalse($this->allowed());
        $this->assertCount(0, $this->service->authorizedConnections($this->consumer, 'LOCAL_SHIPPING'));
    }

    public function test_model_preserves_inactive_default_when_status_is_omitted(): void
    {
        $connection = ProviderConnection::forceCreate([
            'consumer_tenant_id' => $this->consumer->id, 'provider_tenant_id' => $this->provider->id,
            'capability' => 'LOCAL_SHIPPING',
        ]);
        $this->assertSame('inactive', $connection->fresh()->status);
        $this->assertFalse($this->allowed());
    }

    public function test_future_and_expired_connections_are_unavailable(): void
    {
        $connection = $this->connect(['valid_from' => now()->addSecond()]);
        $this->assertFalse($this->allowed());
        $connection->forceFill(['valid_from' => null, 'valid_to' => now()])->save();
        $this->assertFalse($this->allowed());
    }

    public function test_wrong_capability_is_unavailable(): void
    {
        $this->connect(['capability' => 'OTHER_CAPABILITY']);
        $this->assertFalse($this->allowed());
    }

    public function test_duplicate_connection_is_rejected_even_if_inactive(): void
    {
        $this->connect();
        $this->expectException(QueryException::class);
        $this->connect(['status' => 'inactive']);
    }

    public function test_only_authorized_published_active_current_provider_services_are_visible(): void
    {
        $connection = $this->connect();
        $visible = $this->shippingService();
        $this->grant($connection, $visible);
        $this->shippingService(); // Published but not granted.
        foreach ([['published' => false], ['status' => 'inactive'], ['valid_from' => now()->addDay()], ['valid_to' => now()]] as $overrides) {
            $this->grant($connection, $this->shippingService($overrides));
        }
        $this->assertSame([$visible->id], $this->service->authorizedLocalServices($this->consumer, $this->provider)->modelKeys());
        $connection->forceFill(['status' => 'inactive'])->save();
        $this->assertCount(0, $this->service->authorizedLocalServices($this->consumer, $this->provider));
    }

    public function test_tenant_without_connection_cannot_consume(): void
    {
        $this->assertFalse($this->allowed());
        $this->assertCount(0, $this->service->authorizedLocalServices($this->consumer, $this->provider));
        $this->assertCount(0, $this->service->currentConnections('LOCAL_SHIPPING'));
        $this->assertNull(app(TenantContext::class)->current());
    }

    public function test_two_consumers_are_isolated_and_context_is_unchanged(): void
    {
        $other = $this->tenant('other');
        $connection = $this->connect();
        $shipping = $this->shippingService();
        $this->grant($connection, $shipping);
        app(TenantContext::class)->set($other);
        $this->assertTrue($this->allowed());
        $this->assertFalse($this->service->canConsume($other, $this->provider, 'LOCAL_SHIPPING'));
        $this->assertCount(0, $this->service->authorizedLocalServices($other, $this->provider));
        $this->assertCount(0, $this->service->currentConnections('LOCAL_SHIPPING'));
        $second = $this->connect(['consumer_tenant_id' => $other->id]);
        $this->assertTrue($this->service->canConsume($other, $this->provider, 'LOCAL_SHIPPING'));
        $this->assertCount(0, $this->service->authorizedLocalServices($other, $this->provider));
        $this->grant($second, $shipping);
        $this->assertSame([$shipping->id], $this->service->authorizedLocalServices($other, $this->provider)->modelKeys());
        $this->assertSame($other, app(TenantContext::class)->current());
    }

    public function test_cross_provider_service_grant_is_rejected_by_database(): void
    {
        $connection = $this->connect();
        $foreign = $this->shippingService(['tenant_id' => $this->consumer->id]);
        $this->expectException(QueryException::class);
        $this->grant($connection, $foreign);
    }

    public function test_local_service_cannot_be_granted_to_other_capability(): void
    {
        $connection = $this->connect(['capability' => 'OTHER_CAPABILITY']);
        $this->expectException(QueryException::class);
        $this->grant($connection, $this->shippingService());
    }

    public function test_missing_tenant_is_rejected_by_foreign_key(): void
    {
        $this->expectException(QueryException::class);
        $this->connect(['provider_tenant_id' => 99999]);
    }

    public function test_invalid_validity_interval_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->connect(['valid_from' => now()->addSecond(), 'valid_to' => now()]);
    }

    public function test_invalid_status_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->connect(['status' => 'unknown']);
    }

    public function test_invalid_capability_is_rejected_by_model(): void
    {
        $this->expectException(ValidationException::class);
        $this->connect(['capability' => 'local_shipping']);
    }

    public function test_equal_validity_bounds_are_storable_but_never_authorize(): void
    {
        $this->connect(['valid_from' => now(), 'valid_to' => now()]);
        $this->assertFalse($this->allowed());
    }

    public function test_database_check_still_rejects_self_connection_when_supported(): void
    {
        $connection = $this->connect();
        $this->expectException(QueryException::class);
        DB::table('network_provider_connections')->where('id', $connection->id)
            ->update(['provider_tenant_id' => $this->consumer->id]);
    }

    public function test_model_validations_do_not_depend_on_checks_for_create_or_update(): void
    {
        DB::statement('PRAGMA ignore_check_constraints = ON');
        try {
            foreach ([
                ['provider_tenant_id' => $this->consumer->id],
                ['valid_from' => now()->addDay(), 'valid_to' => now()],
                ['status' => 'ACTIVE'], ['status' => 'active '],
                ['capability' => 'local_shipping'], ['capability' => 'LOCAL_SHIPPING '],
            ] as $invalid) {
                try {
                    $this->connect($invalid);
                    $this->fail('Invalid model write must be rejected without CHECK.');
                } catch (ValidationException) {
                    $this->assertDatabaseCount('network_provider_connections', 0);
                }
            }
            $connection = $this->connect();
            try {
                $connection->forceFill(['provider_tenant_id' => $this->consumer->id])->save();
                $this->fail('Invalid model update must be rejected without CHECK.');
            } catch (ValidationException) {
                $this->assertSame($this->provider->id, $connection->fresh()->provider_tenant_id);
            }
        } finally {
            DB::statement('PRAGMA ignore_check_constraints = OFF');
        }
    }

    public function test_sql_bypass_without_checks_cannot_authorize_invalid_connections(): void
    {
        $connection = $this->connect();
        DB::statement('PRAGMA ignore_check_constraints = ON');
        try {
            foreach ([
                ['provider_tenant_id' => $this->consumer->id],
                ['valid_from' => now()->addDay(), 'valid_to' => now()->subDay()],
                ['status' => 'ACTIVE'], ['status' => 'active '],
                ['capability' => 'local_shipping'], ['capability' => 'LOCAL_SHIPPING '],
            ] as $invalid) {
                DB::table('network_provider_connections')->where('id', $connection->id)->update(array_replace([
                    'provider_tenant_id' => $this->provider->id, 'valid_from' => null,
                    'valid_to' => null, 'status' => 'active', 'capability' => 'LOCAL_SHIPPING',
                ], $invalid));
                $this->assertFalse($this->allowed());
                $this->assertCount(0, $this->service->authorizedConnections($this->consumer, 'LOCAL_SHIPPING'));
                $this->assertCount(0, $this->service->authorizedLocalServices($this->consumer, $this->provider));
            }
            $this->assertCount(0, $this->service->authorizedConnections($this->consumer, 'local_shipping'));
        } finally {
            DB::statement('PRAGMA ignore_check_constraints = OFF');
        }
    }

    public function test_connection_with_grants_cannot_be_deleted_implicitly(): void
    {
        $connection = $this->connect();
        $this->grant($connection, $this->shippingService());
        $this->expectException(QueryException::class);
        $connection->delete();
    }

    public function test_duplicate_service_grant_is_rejected(): void
    {
        $connection = $this->connect();
        $shipping = $this->shippingService();
        $this->grant($connection, $shipping);
        $this->expectException(QueryException::class);
        $this->grant($connection, $shipping);
    }

    public function test_granted_service_cannot_be_reassigned_to_another_tenant(): void
    {
        $connection = $this->connect();
        $shipping = $this->shippingService();
        $this->grant($connection, $shipping);
        $this->expectException(QueryException::class);
        $shipping->update(['tenant_id' => $this->consumer->id]);
    }

    public function test_multiple_providers_need_independent_connections(): void
    {
        $otherProvider = $this->tenant('other-provider');
        $first = $this->connect();
        $this->assertFalse($this->service->canConsume($this->consumer, $otherProvider, 'LOCAL_SHIPPING'));
        $second = $this->connect(['provider_tenant_id' => $otherProvider->id]);
        $this->assertTrue($this->service->canConsume($this->consumer, $otherProvider, 'LOCAL_SHIPPING'));
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $this->service->authorizedConnections($this->consumer, 'LOCAL_SHIPPING')->modelKeys());
    }

    public function test_suspended_provider_or_consumer_fails_closed(): void
    {
        $this->connect();
        $this->provider->update(['status' => 'suspended']);
        $this->assertFalse($this->allowed());
        $this->provider->update(['status' => 'active']);
        $this->consumer->update(['status' => 'inactive']);
        $this->assertFalse($this->allowed());
    }

    public function test_migration_rolls_back_without_removing_local_services(): void
    {
        $this->grant($this->connect(), $this->shippingService());
        $this->migration->down();
        $this->assertFalse(Schema::hasTable('network_provider_connections'));
        $this->assertFalse(Schema::hasTable('network_provider_connection_services'));
        $this->assertDatabaseCount('network_tenants', 2);
        $this->assertDatabaseCount('local_shipping_services', 1);
        $indexes = collect(DB::select("PRAGMA index_list('local_shipping_services')"))->pluck('name');
        $this->assertNotContains('local_service_owner_uq', $indexes);
        $this->migration->up();
        $this->connect();
        $this->assertTrue($this->allowed());
    }

    public function test_mysql_and_mariadb_ddl_selects_checks_by_server_version(): void
    {
        // SQL generation test only; this does not execute DDL on these versions.
        $versions = [
            '5.7.44' => false, '8.0.15' => false, '8.0.16' => true,
            '5.5.5-10.1.48-MariaDB' => false, '5.5.5-10.4.32-MariaDB' => true,
        ];
        Schema::shouldReceive('table')->times(3 * count($versions))->andReturnNull();
        $connection = \Mockery::mock(\Illuminate\Database\Connection::class);
        $connection->shouldReceive('getDriverName')->times(count($versions))->andReturn('mysql');
        DB::shouldReceive('connection')->times(count($versions))->andReturn($connection);
        DB::shouldReceive('selectOne')->with('SELECT VERSION() AS version')
            ->times(count($versions))->andReturn(...array_map(fn ($v) => (object) ['version' => $v], array_keys($versions)));
        $statements = [];
        DB::shouldReceive('statement')->times(2 * count($versions))->andReturnUsing(function ($sql) use (&$statements) {
            $statements[] = $sql;
            return true;
        });
        foreach ($versions as $version => $checks) {
            $this->migration->up();
            [$parent, $child] = array_slice($statements, -2);
            $this->assertSame($checks, str_contains($parent, 'CHECK ('), $version);
            $this->assertSame($checks, str_contains($child, 'CHECK ('), $version);
            $this->assertStringContainsString('FOREIGN KEY (consumer_tenant_id)', $parent);
            $this->assertStringContainsString('UNIQUE (consumer_tenant_id, provider_tenant_id, capability)', $parent);
            $this->assertStringContainsString('FOREIGN KEY (local_shipping_service_id, provider_tenant_id)', $child);
            $this->assertStringContainsString('TIMESTAMP NULL DEFAULT NULL', $parent);
            $this->assertStringContainsString('CHARACTER SET ascii COLLATE ascii_bin', $parent);
            $this->assertStringContainsString('ENGINE=InnoDB', $child);
            $this->assertStringNotContainsString('ON DELETE CASCADE', $child);
        }
    }

    private function tenant(string $slug): Tenant
    {
        return Tenant::create(['name' => $slug, 'slug' => $slug, 'status' => 'active']);
    }

    private function connect(array $overrides = []): ProviderConnection
    {
        return ProviderConnection::forceCreate(array_replace([
            'consumer_tenant_id' => $this->consumer->id,
            'provider_tenant_id' => $this->provider->id,
            'capability' => 'LOCAL_SHIPPING', 'status' => 'active',
        ], $overrides));
    }

    private function shippingService(array $overrides = []): LocalShippingService
    {
        return LocalShippingService::create(array_replace([
            'tenant_id' => $this->provider->id, 'code' => 'LOCAL', 'name' => 'Local',
            'status' => 'active', 'published' => true,
        ], $overrides));
    }

    private function grant(ProviderConnection $connection, LocalShippingService $service): void
    {
        DB::table('network_provider_connection_services')->insert([
            'connection_id' => $connection->id, 'provider_tenant_id' => $connection->provider_tenant_id,
            'capability' => 'LOCAL_SHIPPING', 'local_shipping_service_id' => $service->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function allowed(): bool
    {
        return $this->service->canConsume($this->consumer, $this->provider, 'LOCAL_SHIPPING');
    }

}
