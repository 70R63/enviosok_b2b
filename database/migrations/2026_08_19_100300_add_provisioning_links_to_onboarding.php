<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saas_onboarding_applications', function (Blueprint $table): void {
            $table->foreignId('legacy_empresa_id')->nullable()->after('owner_user_id');
            $table->foreign('legacy_empresa_id', 'saas_onboarding_legacy_empresa_fk')
                ->references('id')->on('empresas');
        });
        Schema::table('tenant_saas_orders', function (Blueprint $table): void {
            $table->foreignId('onboarding_application_id')->nullable()->after('tenant_id');
            $table->foreign('onboarding_application_id', 'saas_order_onboarding_fk')
                ->references('id')->on('saas_onboarding_applications');
            $table->unique('onboarding_application_id', 'saas_order_onboarding_uq');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_saas_orders', function (Blueprint $table): void {
            $table->dropUnique('saas_order_onboarding_uq');
            $table->dropForeign('saas_order_onboarding_fk');
            $table->dropColumn('onboarding_application_id');
        });
        Schema::table('saas_onboarding_applications', function (Blueprint $table): void {
            $table->dropForeign('saas_onboarding_legacy_empresa_fk');
            $table->dropColumn('legacy_empresa_id');
        });
    }
};
