<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('network_tenants', function (Blueprint $table): void {
            // Commercial assignment only. This is not a recurring subscription.
            $table->foreignId('current_plan_id')->nullable()->after('status')
                ->constrained('network_plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('network_tenants', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('current_plan_id');
        });
    }
};
