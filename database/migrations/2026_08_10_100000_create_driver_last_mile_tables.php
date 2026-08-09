<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_driver_profiles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('driver_profile_uuid_uq');
            $table->foreignId('tenant_id');
            $table->foreignId('user_id');
            $table->string('code', 40);
            $table->string('status', 20)->default('ACTIVE');
            $table->string('vehicle_label', 120)->nullable();
            $table->timestamps();
            $table->foreign('tenant_id', 'driver_profile_tenant_fk')->references('id')->on('network_tenants');
            $table->foreign('user_id', 'driver_profile_user_fk')->references('id')->on('users');
            $table->unique(['tenant_id', 'user_id'], 'driver_profile_tenant_user_uq');
            $table->unique(['tenant_id', 'code'], 'driver_profile_tenant_code_uq');
        });

        Schema::create('local_driver_assignments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('driver_assignment_uuid_uq');
            $table->foreignId('tenant_id');
            $table->foreignId('local_shipment_id');
            $table->unsignedBigInteger('active_shipment_id')->nullable()->unique('driver_assignment_active_ship_uq');
            $table->foreignId('driver_profile_id');
            $table->foreignId('assigned_by_user_id');
            $table->timestamp('assigned_at');
            $table->timestamp('unassigned_at')->nullable();
            $table->string('status', 20)->default('ACTIVE');
            $table->timestamps();
            $table->foreign('tenant_id', 'driver_assignment_tenant_fk')->references('id')->on('network_tenants');
            $table->foreign('local_shipment_id', 'driver_assignment_ship_fk')->references('id')->on('local_shipments');
            $table->foreign('active_shipment_id', 'driver_assignment_active_fk')->references('id')->on('local_shipments');
            $table->foreign('driver_profile_id', 'driver_assignment_profile_fk')->references('id')->on('tenant_driver_profiles');
            $table->foreign('assigned_by_user_id', 'driver_assignment_actor_fk')->references('id')->on('users');
            $table->index(['tenant_id', 'driver_profile_id', 'status'], 'driver_assignment_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('local_driver_assignments');
        Schema::dropIfExists('tenant_driver_profiles');
    }
};
