<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenant_customer_profiles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('tenant_customer_uuid_uq');
            $table->foreignId('tenant_id');
            $table->foreignId('user_id');
            $table->string('status', 20)->default('active');
            $table->string('display_name', 160)->nullable();
            $table->string('phone', 30)->nullable();
            $table->timestamps();
            $table->foreign('tenant_id', 'tenant_customer_tenant_fk')->references('id')->on('network_tenants');
            $table->foreign('user_id', 'tenant_customer_user_fk')->references('id')->on('users');
            $table->unique(['tenant_id', 'user_id'], 'tenant_customer_tenant_user_uq');
            $table->index(['tenant_id', 'status'], 'tenant_customer_status_idx');
        });

        Schema::table('network_tenant_operations', function (Blueprint $table): void {
            $table->foreignId('customer_profile_id')->nullable()->after('created_by_user_id');
            $table->foreign('customer_profile_id', 'tenant_operation_customer_fk')->references('id')->on('tenant_customer_profiles');
            $table->index(['tenant_id', 'customer_profile_id'], 'tenant_operation_customer_idx');
        });
    }

    public function down(): void
    {
        Schema::table('network_tenant_operations', function (Blueprint $table): void {
            $table->dropForeign('tenant_operation_customer_fk');
            $table->dropIndex('tenant_operation_customer_idx');
            $table->dropColumn('customer_profile_id');
        });
        Schema::dropIfExists('tenant_customer_profiles');
    }
};
