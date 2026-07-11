<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_clients', function (Blueprint $table) {
            if (Schema::hasColumn('api_clients', 'status')) {
                $table->dropColumn('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('api_clients', function (Blueprint $table) {
            if (!Schema::hasColumn('api_clients', 'status')) {
                $table->string('status')->default('active')->after('monthly_limit');
            }
        });
    }
};