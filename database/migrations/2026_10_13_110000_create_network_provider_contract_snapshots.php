<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        if (! in_array($driver, ['sqlite', 'mysql', 'mariadb'], true)) {
            throw new RuntimeException('Provider contract snapshots require SQLite or MySQL/MariaDB.');
        }
        Schema::table('network_provider_connections', function (Blueprint $t): void {
            $t->unique(['id', 'consumer_tenant_id', 'provider_tenant_id', 'capability'], 'net_provider_contract_scope_uq');
        });
        Schema::create('network_provider_quote_snapshots', function (Blueprint $t) use ($driver): void {
            $t->engine = 'InnoDB';
            $t->id();
            $t->uuid('uuid')->unique('net_provider_offer_uuid_uq');
            $t->foreignId('b2c_cotizacion_id');
            $t->foreignId('consumer_tenant_id');
            $t->foreignId('provider_tenant_id');
            $t->foreignId('provider_connection_id');
            $t->foreignId('local_shipping_service_id');
            $t->foreignId('local_shipping_pricing_rule_id');
            $t->foreignId('commercial_pricing_rule_id');
            $capability = $t->string('capability', 64);
            if ($driver !== 'sqlite') {
                $capability->charset('ascii')->collation('ascii_bin');
            }
            $t->string('quote_source', 64);
            $t->char('request_fingerprint', 64);
            $t->char('owner_binding', 64);
            foreach (['package_snapshot', 'origin_snapshot', 'destination_snapshot', 'service_snapshot', 'tariff_snapshot', 'commercial_rule_snapshot'] as $field) {
                $t->json($field);
            }
            foreach (['provider_cost', 'subtotal_amount', 'tax_amount', 'total_amount', 'profit_amount'] as $field) {
                $t->decimal($field, 12, 2);
            }
            $t->decimal('tax_rate', 5, 4);
            $t->char('currency', 3);
            $t->timestamp('valid_from')->nullable()->default(null);
            // DATETIME avoids legacy implicit TIMESTAMP defaults under strict SQL.
            $t->dateTime('expires_at');
            $t->timestamps();
            $t->unique(['id', 'b2c_cotizacion_id'], 'net_provider_offer_quote_uq');
            // Unselected offers must not block existing B2C quote deletion.
            // A selected contract still restricts deletion through its own FKs.
            $t->foreign('b2c_cotizacion_id', 'net_provider_offer_b2c_fk')->references('id')->on('b2c_cotizaciones')->cascadeOnDelete();
            $t->foreign('consumer_tenant_id', 'net_provider_offer_consumer_fk')->references('id')->on('network_tenants');
            $t->foreign('provider_tenant_id', 'net_provider_offer_provider_fk')->references('id')->on('network_tenants');
            $t->foreign('local_shipping_pricing_rule_id', 'net_provider_offer_tariff_fk')->references('id')->on('local_shipping_pricing_rules');
            $t->foreign('commercial_pricing_rule_id', 'net_provider_offer_pricing_fk')->references('id')->on('zigo_pricing_rules');
            $t->foreign(['provider_connection_id', 'consumer_tenant_id', 'provider_tenant_id', 'capability'], 'net_provider_offer_connection_fk')
                ->references(['id', 'consumer_tenant_id', 'provider_tenant_id', 'capability'])->on('network_provider_connections');
            $t->foreign(['local_shipping_service_id', 'provider_tenant_id'], 'net_provider_offer_service_fk')
                ->references(['id', 'tenant_id'])->on('local_shipping_services');
            $t->index(['consumer_tenant_id', 'b2c_cotizacion_id', 'expires_at'], 'net_provider_offer_lookup_idx');
        });
        Schema::create('network_provider_contracts', function (Blueprint $t): void {
            $t->engine = 'InnoDB';
            $t->id();
            $t->uuid('uuid')->unique('net_provider_contract_uuid_uq');
            $t->foreignId('quote_snapshot_id')->unique('net_provider_contract_offer_uq');
            $t->foreignId('b2c_cotizacion_id')->unique('net_provider_contract_b2c_uq');
            $t->foreignId('selected_by_user_id')->nullable();
            $t->dateTime('selected_at');
            $t->timestamps();
            $t->foreign(['quote_snapshot_id', 'b2c_cotizacion_id'], 'net_provider_contract_offer_fk')
                ->references(['id', 'b2c_cotizacion_id'])->on('network_provider_quote_snapshots');
            $t->foreign('b2c_cotizacion_id', 'net_provider_contract_b2c_fk')->references('id')->on('b2c_cotizaciones');
            $t->foreign('selected_by_user_id', 'net_provider_contract_user_fk')->references('id')->on('users');
        });
        // No TRIGGER privilege required; models enforce functional immutability.
    }

    public function down(): void
    {
        // Drop children before parents; no trigger DDL required.
        Schema::dropIfExists('network_provider_contracts');
        Schema::dropIfExists('network_provider_quote_snapshots');
        Schema::table('network_provider_connections', fn (Blueprint $t) => $t->dropUnique('net_provider_contract_scope_uq'));
    }
};
