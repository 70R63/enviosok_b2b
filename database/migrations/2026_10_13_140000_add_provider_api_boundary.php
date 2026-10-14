<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function nullable(string $table, string $column, bool $nullable): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE {$table} MODIFY {$column} BIGINT UNSIGNED ".($nullable ? 'NULL' : 'NOT NULL'));
        } else {
            Schema::table($table, fn (Blueprint $t) => $t->unsignedBigInteger($column)->nullable($nullable)->change());
        }
    }

    public function up(): void
    {
        Schema::table('tenant_api_clients', fn (Blueprint $t) => $t->unique(['id', 'tenant_id'], 'pr_api_client_owner_uq'));
        Schema::table('network_provider_connections', fn (Blueprint $t) => $t->unique(['id', 'provider_tenant_id'], 'pr_api_connection_owner_uq'));
        Schema::create('network_provider_integrations', function (Blueprint $t): void {
            $t->engine = 'InnoDB';
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('provider_tenant_id');
            $t->foreignId('api_client_id')->unique();
            $t->foreignId('provider_connection_id');
            $t->string('status', 16)->default('inactive');
            $t->string('adapter', 64)->default('disabled');
            $t->dateTime('valid_from')->nullable();
            $t->dateTime('valid_to')->nullable();
            $t->timestamps();
            $t->foreign(['api_client_id', 'provider_tenant_id'], 'pr_api_client_owner_fk')->references(['id', 'tenant_id'])->on('tenant_api_clients');
            $t->foreign(['provider_connection_id', 'provider_tenant_id'], 'pr_api_connection_owner_fk')->references(['id', 'provider_tenant_id'])->on('network_provider_connections');
        });
        Schema::create('network_provider_external_references', function (Blueprint $t): void {
            $t->engine = 'InnoDB';
            $t->id();
            $t->foreignId('integration_id');
            $t->foreignId('provider_fulfillment_id');
            $reference = $t->string('external_reference', 160);
            if (DB::connection()->getDriverName() === 'mysql') {
                $reference->collation('utf8mb4_bin');
            } $t->timestamps();
            $t->foreign('integration_id', 'pr_api_ref_integration_fk')->references('id')->on('network_provider_integrations');
            $t->foreign('provider_fulfillment_id', 'pr_api_ref_fulfillment_fk')->references('id')->on('network_provider_fulfillments');
            $t->unique(['integration_id', 'provider_fulfillment_id'], 'pr_api_ref_fulfillment_uq');
            $t->unique(['integration_id', 'external_reference'], 'pr_api_ref_external_uq');
        });
        foreach (['network_provider_fulfillment_decisions' => 'actor_user_id', 'local_driver_assignments' => 'assigned_by_user_id', 'tenant_api_audit_events' => 'actor_user_id'] as $table => $column) {
            $this->nullable($table, $column, true);
        }
        foreach (['network_provider_fulfillment_decisions' => 'pr_api_decision_actor_fk', 'local_driver_assignments' => 'pr_api_assignment_actor_fk'] as $table => $name) {
            Schema::table($table, function (Blueprint $t) use ($name): void {
                $t->foreignId('actor_api_client_id')->nullable();
                $t->foreign('actor_api_client_id', $name)->references('id')->on('tenant_api_clients');
            });
        }
        Schema::table('tenant_api_audit_events', fn (Blueprint $t) => $t->string('channel', 24)->default('TENANT_UI'));
        $this->nullable('tenant_api_webhook_deliveries', 'webhook_endpoint_id', true);
        Schema::table('tenant_api_webhook_deliveries', function (Blueprint $t): void {
            $t->foreignId('provider_integration_id')->nullable()->constrained('network_provider_integrations');
            $t->foreignId('provider_fulfillment_id')->nullable()->constrained('network_provider_fulfillments');
            $t->uuid('lease_token')->nullable();
            $t->dateTime('lease_expires_at')->nullable();
            $t->unique(['provider_integration_id', 'resource_uuid', 'event_type'], 'pr_api_outbox_resource_uq');
        });
    }

    public function down(): void
    {
        // Never erase external actor identity or invent a human on downgrade.
        if (DB::table('network_provider_fulfillment_decisions')->whereNull('actor_user_id')->exists()
            || DB::table('local_driver_assignments')->whereNull('assigned_by_user_id')->exists()
            || DB::table('tenant_api_audit_events')->whereNull('actor_user_id')->exists()
            || DB::table('tenant_api_webhook_deliveries')->whereNotNull('provider_integration_id')->exists()
            || DB::table('network_provider_external_references')->exists()) {
            throw new RuntimeException('Export/migrate provider API history before schema rollback. No changes applied.');
        }
        Schema::table('tenant_api_webhook_deliveries', function (Blueprint $t): void {
            $t->dropForeign(['provider_integration_id']);
            $t->dropForeign(['provider_fulfillment_id']);
            $t->dropUnique('pr_api_outbox_resource_uq');
            $t->dropColumn(['provider_integration_id', 'provider_fulfillment_id', 'lease_token', 'lease_expires_at']);
        });
        $this->nullable('tenant_api_webhook_deliveries', 'webhook_endpoint_id', false);
        foreach (['network_provider_fulfillment_decisions' => 'pr_api_decision_actor_fk', 'local_driver_assignments' => 'pr_api_assignment_actor_fk'] as $table => $name) {
            Schema::table($table, function (Blueprint $t) use ($name): void {
                $t->dropForeign(DB::connection()->getDriverName() === 'sqlite' ? ['actor_api_client_id'] : $name);
                $t->dropColumn('actor_api_client_id');
            });
        }
        Schema::table('tenant_api_audit_events', fn (Blueprint $t) => $t->dropColumn('channel'));
        foreach (['network_provider_fulfillment_decisions' => 'actor_user_id', 'local_driver_assignments' => 'assigned_by_user_id', 'tenant_api_audit_events' => 'actor_user_id'] as $table => $column) {
            $this->nullable($table, $column, false);
        }
        Schema::dropIfExists('network_provider_external_references');
        Schema::dropIfExists('network_provider_integrations');
        Schema::table('network_provider_connections', fn (Blueprint $t) => $t->dropUnique('pr_api_connection_owner_uq'));
        Schema::table('tenant_api_clients', fn (Blueprint $t) => $t->dropUnique('pr_api_client_owner_uq'));
    }
};
