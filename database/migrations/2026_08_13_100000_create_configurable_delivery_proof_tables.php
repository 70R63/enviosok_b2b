<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_delivery_proof_options', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('tdpo_uuid_uq');
            $table->foreignId('tenant_id');
            $table->string('code', 40);
            $table->string('name', 120);
            $table->string('description', 300)->nullable();
            $table->boolean('require_receiver_name')->default(false);
            $table->boolean('require_receiver_type')->default(false);
            $table->boolean('require_signature')->default(false);
            $table->boolean('require_photo')->default(false);
            $table->boolean('require_gps')->default(false);
            $table->string('receiver_policy', 30)->default('ANY_PERSON_AT_ADDRESS');
            $table->unsignedSmallInteger('max_delivery_attempts')->default(2);
            $table->decimal('surcharge_amount', 10, 2)->nullable();
            $table->char('currency', 3)->default('MXN');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->foreign('tenant_id', 'tdpo_tenant_fk')->references('id')->on('network_tenants');
            $table->unique(['tenant_id', 'code'], 'tdpo_tenant_code_uq');
            $table->index(['tenant_id', 'is_active', 'sort_order'], 'tdpo_tenant_active_idx');
        });

        Schema::create('local_shipment_delivery_requirements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('local_shipment_id')->unique('lsdr_shipment_uq');
            $table->string('proof_option_code_snapshot', 40);
            $table->string('proof_option_name_snapshot', 120);
            $table->boolean('require_receiver_name');
            $table->boolean('require_receiver_type');
            $table->boolean('require_signature');
            $table->boolean('require_photo');
            $table->boolean('require_gps');
            $table->string('receiver_policy', 30);
            $table->unsignedSmallInteger('max_delivery_attempts');
            $table->decimal('surcharge_amount_snapshot', 10, 2)->nullable();
            $table->char('currency_snapshot', 3);
            $table->timestamp('created_at')->nullable();
            $table->foreign('tenant_id', 'lsdr_tenant_fk')->references('id')->on('network_tenants');
            $table->foreign('local_shipment_id', 'lsdr_shipment_fk')->references('id')->on('local_shipments');
            $table->index(['tenant_id', 'proof_option_code_snapshot'], 'lsdr_tenant_option_idx');
        });

        $now = now();
        foreach (DB::table('network_tenants')->orderBy('id')->get(['id']) as $tenant) {
            DB::table('tenant_delivery_proof_options')->insertOrIgnore([
                'uuid' => (string) Str::uuid(), 'tenant_id' => $tenant->id, 'code' => 'DEFAULT', 'name' => 'Evidencia completa',
                'description' => 'Compatibilidad: receptor, fotografía, firma y GPS.', 'require_receiver_name' => true,
                'require_receiver_type' => true, 'require_signature' => true, 'require_photo' => true, 'require_gps' => true,
                'receiver_policy' => 'ANY_PERSON_AT_ADDRESS', 'max_delivery_attempts' => 2, 'surcharge_amount' => 0,
                'currency' => 'MXN', 'is_default' => true, 'is_active' => true, 'sort_order' => 0,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        foreach (DB::table('local_shipments')->orderBy('id')->get(['id', 'tenant_id']) as $shipment) {
            DB::table('local_shipment_delivery_requirements')->insertOrIgnore([
                'tenant_id' => $shipment->tenant_id, 'local_shipment_id' => $shipment->id,
                'proof_option_code_snapshot' => 'DEFAULT', 'proof_option_name_snapshot' => 'Evidencia completa',
                'require_receiver_name' => true, 'require_receiver_type' => true, 'require_signature' => true,
                'require_photo' => true, 'require_gps' => true, 'receiver_policy' => 'ANY_PERSON_AT_ADDRESS',
                'max_delivery_attempts' => 2, 'surcharge_amount_snapshot' => 0, 'currency_snapshot' => 'MXN', 'created_at' => $now,
            ]);
        }

        Schema::table('local_delivery_failed_attempts', function (Blueprint $table): void {
            $table->unsignedSmallInteger('attempt_number')->nullable()->after('driver_profile_id');
        });
        $currentShipment = null;
        $number = 0;
        foreach (DB::table('local_delivery_failed_attempts')->orderBy('local_shipment_id')->orderBy('id')->get(['id', 'local_shipment_id']) as $attempt) {
            if ($currentShipment !== $attempt->local_shipment_id) { $currentShipment = $attempt->local_shipment_id; $number = 0; }
            DB::table('local_delivery_failed_attempts')->where('id', $attempt->id)->update(['attempt_number' => ++$number]);
        }
        Schema::table('local_delivery_failed_attempts', function (Blueprint $table): void {
            $table->unique(['local_shipment_id', 'attempt_number'], 'local_fail_shipment_attempt_uq');
        });

    }

    public function down(): void
    {
        Schema::table('local_delivery_failed_attempts', function (Blueprint $table): void {
            $table->dropUnique('local_fail_shipment_attempt_uq');
            $table->dropColumn('attempt_number');
        });
        Schema::dropIfExists('local_shipment_delivery_requirements');
        Schema::dropIfExists('tenant_delivery_proof_options');
    }
};
