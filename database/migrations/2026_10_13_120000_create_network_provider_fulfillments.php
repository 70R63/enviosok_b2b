<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('b2c_cotizaciones', function (Blueprint $t): void {
            $t->string('remitente_referencias', 255)->nullable();
            $t->string('destinatario_referencias', 255)->nullable();
            $t->dateTime('provider_fulfillment_attempted_at')->nullable();
            $t->dateTime('provider_confirmation_claimed_at')->nullable();
        });
        Schema::table('network_provider_contracts', fn (Blueprint $t) => $t->unique(['id', 'quote_snapshot_id'], 'net_pf_contract_scope_uq'));
        Schema::table('network_provider_quote_snapshots', fn (Blueprint $t) => $t->unique(['id', 'consumer_tenant_id', 'provider_tenant_id'], 'net_pf_offer_scope_uq'));
        Schema::table('network_tenant_operations', fn (Blueprint $t) => $t->unique(['id', 'tenant_id'], 'net_pf_operation_scope_uq'));
        Schema::table('local_shipments', fn (Blueprint $t) => $t->unique(['id', 'tenant_operation_id', 'tenant_id'], 'net_pf_shipment_scope_uq'));
        Schema::create('network_provider_fulfillments', function (Blueprint $t): void {
            $t->engine = 'InnoDB';
            $t->id();
            $t->uuid('uuid')->unique('net_pf_uuid_uq');
            $t->foreignId('provider_contract_id')->unique('net_pf_contract_uq');
            $t->foreignId('quote_snapshot_id');
            $t->foreignId('consumer_tenant_id');
            $t->foreignId('provider_tenant_id');
            $t->foreignId('tenant_operation_id')->unique('net_pf_operation_uq');
            $t->foreignId('local_shipment_id')->unique('net_pf_shipment_uq');
            $t->dateTime('commercial_confirmed_at');
            $t->dateTime('pickup_requested_at');
            $t->timestamps();
            $t->foreign(['provider_contract_id', 'quote_snapshot_id'], 'net_pf_contract_fk')->references(['id', 'quote_snapshot_id'])->on('network_provider_contracts');
            $t->foreign(['quote_snapshot_id', 'consumer_tenant_id', 'provider_tenant_id'], 'net_pf_offer_fk')->references(['id', 'consumer_tenant_id', 'provider_tenant_id'])->on('network_provider_quote_snapshots');
            $t->foreign(['tenant_operation_id', 'provider_tenant_id'], 'net_pf_operation_fk')->references(['id', 'tenant_id'])->on('network_tenant_operations');
            $t->foreign(['local_shipment_id', 'tenant_operation_id', 'provider_tenant_id'], 'net_pf_shipment_fk')->references(['id', 'tenant_operation_id', 'tenant_id'])->on('local_shipments');
            $t->index(['consumer_tenant_id', 'created_at'], 'net_pf_consumer_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_provider_fulfillments');
        Schema::table('local_shipments', fn (Blueprint $t) => $t->dropUnique('net_pf_shipment_scope_uq'));
        Schema::table('network_tenant_operations', fn (Blueprint $t) => $t->dropUnique('net_pf_operation_scope_uq'));
        Schema::table('network_provider_quote_snapshots', fn (Blueprint $t) => $t->dropUnique('net_pf_offer_scope_uq'));
        Schema::table('network_provider_contracts', fn (Blueprint $t) => $t->dropUnique('net_pf_contract_scope_uq'));
        Schema::table('b2c_cotizaciones', fn (Blueprint $t) => $t->dropColumn(['remitente_referencias', 'destinatario_referencias', 'provider_fulfillment_attempted_at', 'provider_confirmation_claimed_at']));
    }
};
