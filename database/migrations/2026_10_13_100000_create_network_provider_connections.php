<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // CHECK is defense in depth. Model validation and authorization queries
        // also enforce business rules on engines that do not enforce CHECK.
        $driver = DB::connection()->getDriverName();
        if (! in_array($driver, ['sqlite', 'mysql', 'mariadb'], true)) {
            throw new RuntimeException('Provider connections require SQLite or MySQL/MariaDB.');
        }
        $supportsChecks = $driver === 'sqlite';
        if ($driver !== 'sqlite') {
            $version = DB::selectOne('SELECT VERSION() AS version')->version;
            $maria = stripos($version, 'MariaDB') !== false;
            preg_match('/(\d+\.\d+\.\d+)(?:-MariaDB)?/', preg_replace('/^5\.5\.5-/', '', $version), $matches);
            if (! isset($matches[1])) {
                throw new RuntimeException('Cannot identify the SQL server version before creating provider connections.');
            }
            $supportsChecks = version_compare($matches[1], $maria ? '10.2.1' : '8.0.16', '>=');
        }
        $id = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
        $capabilityType = $driver === 'sqlite' ? 'VARCHAR(64)' : 'VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin';
        $statusType = $driver === 'sqlite' ? 'VARCHAR(20)' : 'VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin';
        $engine = $driver === 'sqlite' ? '' : ' ENGINE=InnoDB';
        $connectionChecks = $supportsChecks ? "
            CONSTRAINT net_provider_distinct_ck CHECK (consumer_tenant_id <> provider_tenant_id),
            CONSTRAINT net_provider_status_ck CHECK (status IN ('active', 'inactive')),
            CONSTRAINT net_provider_capability_ck CHECK (LENGTH(TRIM(capability)) > 0 AND capability = UPPER(capability)),
            CONSTRAINT net_provider_dates_ck CHECK (valid_from IS NULL OR valid_to IS NULL OR valid_from <= valid_to)," : '';
        $serviceChecks = $supportsChecks ? "
            CONSTRAINT net_provider_service_cap_ck CHECK (capability = 'LOCAL_SHIPPING')," : '';
        DB::statement("CREATE TABLE network_provider_connections (
            id {$id},
            consumer_tenant_id BIGINT UNSIGNED NOT NULL,
            provider_tenant_id BIGINT UNSIGNED NOT NULL,
            capability {$capabilityType} NOT NULL,
            status {$statusType} NOT NULL DEFAULT 'inactive',
            valid_from TIMESTAMP NULL DEFAULT NULL,
            valid_to TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP NULL DEFAULT NULL,
            updated_at TIMESTAMP NULL DEFAULT NULL,
            CONSTRAINT net_provider_consumer_fk FOREIGN KEY (consumer_tenant_id) REFERENCES network_tenants(id),
            CONSTRAINT net_provider_provider_fk FOREIGN KEY (provider_tenant_id) REFERENCES network_tenants(id),
            {$connectionChecks}
            CONSTRAINT net_provider_pair_uq UNIQUE (consumer_tenant_id, provider_tenant_id, capability),
            CONSTRAINT net_provider_identity_uq UNIQUE (id, provider_tenant_id, capability)
        ){$engine}");
        Schema::table('network_provider_connections', function (Blueprint $table): void {
            $table->index(['consumer_tenant_id', 'capability', 'status'], 'net_provider_consumer_idx');
            $table->index('provider_tenant_id', 'net_provider_provider_idx');
        });
        Schema::table('local_shipping_services', function (Blueprint $table): void {
            $table->unique(['id', 'tenant_id'], 'local_service_owner_uq');
        });
        DB::statement("CREATE TABLE network_provider_connection_services (
            id {$id},
            connection_id BIGINT UNSIGNED NOT NULL,
            provider_tenant_id BIGINT UNSIGNED NOT NULL,
            capability {$capabilityType} NOT NULL DEFAULT 'LOCAL_SHIPPING',
            local_shipping_service_id BIGINT UNSIGNED NOT NULL,
            created_at TIMESTAMP NULL DEFAULT NULL,
            updated_at TIMESTAMP NULL DEFAULT NULL,
            {$serviceChecks}
            CONSTRAINT net_provider_service_uq UNIQUE (connection_id, local_shipping_service_id),
            CONSTRAINT net_provider_service_connection_fk FOREIGN KEY (connection_id, provider_tenant_id, capability)
                REFERENCES network_provider_connections(id, provider_tenant_id, capability),
            CONSTRAINT net_provider_service_owner_fk FOREIGN KEY (local_shipping_service_id, provider_tenant_id)
                REFERENCES local_shipping_services(id, tenant_id)
        ){$engine}");
        Schema::table('network_provider_connection_services', function (Blueprint $table): void {
            $table->index(['local_shipping_service_id', 'provider_tenant_id'], 'net_provider_service_owner_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_provider_connection_services');
        Schema::dropIfExists('network_provider_connections');
        Schema::table('local_shipping_services', function (Blueprint $table): void {
            $table->dropUnique('local_service_owner_uq');
        });
    }
};
