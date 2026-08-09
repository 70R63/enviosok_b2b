<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('local_delivery_proofs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('local_pod_uuid_uq');
            $table->foreignId('tenant_id');
            $table->foreignId('local_shipment_id')->unique('local_pod_shipment_uq');
            $table->foreignId('driver_assignment_id');
            $table->foreignId('driver_profile_id');
            $table->string('received_by_name', 120);
            $table->string('receiver_type', 30);
            $table->string('receiver_notes', 300)->nullable();
            $table->string('photo_path', 255)->nullable();
            $table->string('signature_path', 255)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('accuracy_meters', 10, 2)->nullable();
            $table->timestamp('captured_at');
            $table->timestamp('created_at')->nullable();
            $table->foreign('tenant_id', 'local_pod_tenant_fk')->references('id')->on('network_tenants');
            $table->foreign('local_shipment_id', 'local_pod_shipment_fk')->references('id')->on('local_shipments');
            $table->foreign('driver_assignment_id', 'local_pod_assignment_fk')->references('id')->on('local_driver_assignments');
            $table->foreign('driver_profile_id', 'local_pod_driver_fk')->references('id')->on('tenant_driver_profiles');
            $table->index(['tenant_id', 'driver_profile_id', 'captured_at'], 'local_pod_report_idx');
        });

        Schema::create('local_delivery_failed_attempts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('local_fail_uuid_uq');
            $table->foreignId('tenant_id');
            $table->foreignId('local_shipment_id');
            $table->foreignId('driver_assignment_id');
            $table->foreignId('driver_profile_id');
            $table->string('reason', 30);
            $table->string('notes', 500)->nullable();
            $table->string('photo_path', 255)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('accuracy_meters', 10, 2)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->nullable();
            $table->foreign('tenant_id', 'local_fail_tenant_fk')->references('id')->on('network_tenants');
            $table->foreign('local_shipment_id', 'local_fail_shipment_fk')->references('id')->on('local_shipments');
            $table->foreign('driver_assignment_id', 'local_fail_assignment_fk')->references('id')->on('local_driver_assignments');
            $table->foreign('driver_profile_id', 'local_fail_driver_fk')->references('id')->on('tenant_driver_profiles');
            $table->index(['tenant_id', 'local_shipment_id', 'occurred_at'], 'local_fail_report_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('local_delivery_failed_attempts');
        Schema::dropIfExists('local_delivery_proofs');
    }
};
