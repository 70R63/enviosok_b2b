<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_payment_attempts', function (Blueprint $table): void {
            $table->dropForeign('platform_attempt_tenant_fk');
            $table->dropForeign('platform_attempt_order_fk');
        });
        Schema::table('platform_payment_attempts', function (Blueprint $table): void {
            $table->unsignedBigInteger('tenant_id')->nullable()->change();
            $table->unsignedBigInteger('saas_order_id')->nullable()->change();
            $table->foreignId('onboarding_application_id')->nullable()->after('saas_order_id');
            $table->foreign('tenant_id', 'platform_attempt_tenant_fk')
                ->references('id')->on('network_tenants');
            $table->foreign('saas_order_id', 'platform_attempt_order_fk')
                ->references('id')->on('tenant_saas_orders');
            $table->foreign('onboarding_application_id', 'platform_attempt_onboarding_fk')
                ->references('id')->on('saas_onboarding_applications');
            $table->index(
                ['onboarding_application_id', 'status'],
                'platform_attempt_onboarding_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('platform_payment_attempts', function (Blueprint $table): void {
            $table->dropForeign('platform_attempt_onboarding_fk');
            $table->dropIndex('platform_attempt_onboarding_status_idx');
            $table->dropColumn('onboarding_application_id');
            $table->dropForeign('platform_attempt_tenant_fk');
            $table->dropForeign('platform_attempt_order_fk');
        });
        Schema::table('platform_payment_attempts', function (Blueprint $table): void {
            $table->unsignedBigInteger('tenant_id')->nullable(false)->change();
            $table->unsignedBigInteger('saas_order_id')->nullable(false)->change();
            $table->foreign('tenant_id', 'platform_attempt_tenant_fk')
                ->references('id')->on('network_tenants');
            $table->foreign('saas_order_id', 'platform_attempt_order_fk')
                ->references('id')->on('tenant_saas_orders');
        });
    }
};
