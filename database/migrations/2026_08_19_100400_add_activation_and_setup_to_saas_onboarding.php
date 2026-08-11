<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saas_onboarding_applications', function (Blueprint $table): void {
            $table->boolean('owner_is_new')->nullable()->after('legacy_empresa_id');
            $table->timestamp('owner_activation_sent_at')->nullable()->after('activated_at');
            $table->timestamp('owner_activation_completed_at')->nullable()->after('owner_activation_sent_at');
            $table->timestamp('setup_started_at')->nullable()->after('owner_activation_completed_at');
            $table->timestamp('setup_completed_at')->nullable()->after('setup_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('saas_onboarding_applications', function (Blueprint $table): void {
            $table->dropColumn([
                'owner_is_new', 'owner_activation_sent_at', 'owner_activation_completed_at',
                'setup_started_at', 'setup_completed_at',
            ]);
        });
    }
};
