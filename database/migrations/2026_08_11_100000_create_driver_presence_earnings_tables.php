<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_driver_profiles', function (Blueprint $table): void {
            $table->string('availability_status', 20)->default('UNAVAILABLE')->after('status');
            $table->timestamp('last_seen_at')->nullable()->after('availability_status');
            $table->index(['tenant_id', 'status', 'availability_status', 'last_seen_at'], 'drv_presence_candidate_idx');
        });

        Schema::create('driver_compensation_policies', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('drv_comp_policy_uuid_uq');
            $table->foreignId('tenant_id');
            $table->foreignId('driver_profile_id');
            $table->string('compensation_type', 20);
            $table->string('settlement_frequency', 20);
            $table->decimal('amount_per_delivery', 12, 2)->nullable();
            $table->char('currency', 3)->default('MXN');
            $table->foreignId('updated_by_user_id');
            $table->timestamps();
            $table->foreign('tenant_id', 'drv_comp_policy_tenant_fk')->references('id')->on('network_tenants');
            $table->foreign('driver_profile_id', 'drv_comp_policy_profile_fk')->references('id')->on('tenant_driver_profiles');
            $table->foreign('updated_by_user_id', 'drv_comp_policy_user_fk')->references('id')->on('users');
            $table->unique(['tenant_id', 'driver_profile_id'], 'drv_comp_policy_tenant_profile_uq');
        });

        Schema::create('driver_delivery_attributions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('drv_delivery_attr_uuid_uq');
            $table->foreignId('tenant_id');
            $table->foreignId('local_shipment_id')->unique('drv_delivery_attr_ship_uq');
            $table->foreignId('driver_assignment_id')->unique('drv_delivery_attr_assign_uq');
            $table->foreignId('driver_profile_id');
            $table->foreignId('driver_user_id');
            $table->string('driver_code_snapshot', 40);
            $table->timestamp('delivered_at');
            $table->timestamp('created_at')->nullable();
            $table->foreign('tenant_id', 'drv_delivery_attr_tenant_fk')->references('id')->on('network_tenants');
            $table->foreign('local_shipment_id', 'drv_delivery_attr_ship_fk')->references('id')->on('local_shipments');
            $table->foreign('driver_assignment_id', 'drv_delivery_attr_assign_fk')->references('id')->on('local_driver_assignments');
            $table->foreign('driver_profile_id', 'drv_delivery_attr_profile_fk')->references('id')->on('tenant_driver_profiles');
            $table->foreign('driver_user_id', 'drv_delivery_attr_user_fk')->references('id')->on('users');
            $table->index(['tenant_id', 'driver_profile_id', 'delivered_at'], 'drv_delivery_attr_report_idx');
        });

        Schema::create('driver_earning_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('drv_earning_uuid_uq');
            $table->foreignId('tenant_id');
            $table->foreignId('driver_profile_id');
            $table->foreignId('driver_user_id');
            $table->foreignId('local_shipment_id');
            $table->foreignId('driver_assignment_id');
            $table->foreignId('compensation_policy_id');
            $table->string('entry_type', 30);
            $table->string('compensation_type', 20);
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3);
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->nullable();
            $table->foreign('tenant_id', 'drv_earning_tenant_fk')->references('id')->on('network_tenants');
            $table->foreign('driver_profile_id', 'drv_earning_profile_fk')->references('id')->on('tenant_driver_profiles');
            $table->foreign('driver_user_id', 'drv_earning_user_fk')->references('id')->on('users');
            $table->foreign('local_shipment_id', 'drv_earning_ship_fk')->references('id')->on('local_shipments');
            $table->foreign('driver_assignment_id', 'drv_earning_assign_fk')->references('id')->on('local_driver_assignments');
            $table->foreign('compensation_policy_id', 'drv_earning_policy_fk')->references('id')->on('driver_compensation_policies');
            $table->unique(['local_shipment_id', 'driver_profile_id', 'entry_type'], 'drv_earning_delivery_uq');
            $table->index(['tenant_id', 'driver_profile_id', 'occurred_at'], 'drv_earning_report_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_earning_entries');
        Schema::dropIfExists('driver_delivery_attributions');
        Schema::dropIfExists('driver_compensation_policies');
        Schema::table('tenant_driver_profiles', function (Blueprint $table): void {
            $table->dropIndex('drv_presence_candidate_idx');
            $table->dropColumn(['availability_status', 'last_seen_at']);
        });
    }
};
