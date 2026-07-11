<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_clients', function (Blueprint $table) {
            if (!Schema::hasColumn('api_clients', 'plan')) {
                $table->string('plan')->default('free')->after('name');
            }

            if (!Schema::hasColumn('api_clients', 'monthly_limit')) {
                $table->unsignedInteger('monthly_limit')->default(100)->after('plan');
            }

            if (!Schema::hasColumn('api_clients', 'status')) {
                $table->string('status')->default('active')->after('monthly_limit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('api_clients', function (Blueprint $table) {
            if (Schema::hasColumn('api_clients', 'status')) {
                $table->dropColumn('status');
            }

            if (Schema::hasColumn('api_clients', 'monthly_limit')) {
                $table->dropColumn('monthly_limit');
            }

            if (Schema::hasColumn('api_clients', 'plan')) {
                $table->dropColumn('plan');
            }
        });
    }
};