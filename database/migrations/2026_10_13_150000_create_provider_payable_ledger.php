<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('network_provider_fulfillments', fn (Blueprint $t) => $t->unique(['id', 'provider_contract_id', 'quote_snapshot_id', 'consumer_tenant_id', 'provider_tenant_id'], 'np_payable_bridge_uq'));
        Schema::create('network_provider_payables', function (Blueprint $t): void {
            $t->engine = 'InnoDB';
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('provider_fulfillment_id')->unique();
            $t->foreignId('provider_contract_id')->unique();
            $t->foreignId('quote_snapshot_id');
            $t->foreignId('consumer_tenant_id');
            $t->foreignId('provider_tenant_id');
            $t->decimal('provider_net_amount', 12, 2);
            $t->decimal('provider_tax_amount', 12, 2)->nullable();
            $t->decimal('provider_gross_amount', 12, 2)->nullable();
            $t->char('currency', 3);
            $t->dateTime('created_at');
            $t->foreign(['provider_fulfillment_id', 'provider_contract_id', 'quote_snapshot_id', 'consumer_tenant_id', 'provider_tenant_id'], 'np_payable_bridge_fk')
                ->references(['id', 'provider_contract_id', 'quote_snapshot_id', 'consumer_tenant_id', 'provider_tenant_id'])->on('network_provider_fulfillments');
            $t->unique(['id', 'consumer_tenant_id', 'provider_tenant_id', 'currency'], 'np_payable_owner_uq');
            $t->index(['consumer_tenant_id', 'provider_tenant_id', 'created_at'], 'np_payable_list_idx');
        });
        Schema::create('network_provider_settlement_entries', function (Blueprint $t): void {
            $t->engine = 'InnoDB';
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('provider_payable_id');
            $t->foreignId('consumer_tenant_id');
            $t->foreignId('provider_tenant_id');
            $t->decimal('amount', 12, 2);
            $t->char('currency', 3);
            $t->string('payment_reference', 128);
            $t->string('idempotency_key', 128);
            $t->char('request_fingerprint', 64);
            $t->dateTime('paid_at');
            $t->foreignId('actor_user_id')->constrained('users');
            $t->string('notes', 500)->nullable();
            $t->dateTime('created_at');
            $t->foreign(['provider_payable_id', 'consumer_tenant_id', 'provider_tenant_id', 'currency'], 'np_settlement_owner_fk')
                ->references(['id', 'consumer_tenant_id', 'provider_tenant_id', 'currency'])->on('network_provider_payables');
            $t->unique(['provider_payable_id', 'idempotency_key'], 'np_settlement_key_uq');
            $t->unique(['consumer_tenant_id', 'provider_tenant_id', 'currency', 'payment_reference'], 'np_settlement_reference_uq');
        });
        Schema::create('network_provider_payable_events', function (Blueprint $t): void {
            $t->engine = 'InnoDB';
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('provider_payable_id')->constrained('network_provider_payables');
            $t->string('source_reference', 160);
            $t->string('action', 32);
            $t->string('previous_status', 20)->nullable();
            $t->string('status', 20);
            $t->foreignId('actor_user_id')->nullable()->constrained('users');
            $t->string('channel', 24);
            $t->dateTime('occurred_at');
            $t->dateTime('created_at');
            $t->foreignId('settlement_entry_id')->nullable()->constrained('network_provider_settlement_entries');
            $t->unique(['provider_payable_id', 'source_reference'], 'np_payable_event_source_uq');
        });
    }

    public function down(): void
    {
        if (DB::table('network_provider_payables')->exists() || DB::table('network_provider_settlement_entries')->exists() || DB::table('network_provider_payable_events')->exists()) {
            throw new RuntimeException('Export/migrate payable financial history before rollback. No changes applied.');
        }
        Schema::drop('network_provider_payable_events');
        Schema::drop('network_provider_settlement_entries');
        Schema::drop('network_provider_payables');
        Schema::table('network_provider_fulfillments', fn (Blueprint $t) => $t->dropUnique('np_payable_bridge_uq'));
    }
};
