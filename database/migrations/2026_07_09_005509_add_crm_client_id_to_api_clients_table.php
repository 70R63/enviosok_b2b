<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_clients', function (Blueprint $table) {
            if (!Schema::hasColumn('api_clients', 'crm_client_id')) {
                $table->unsignedBigInteger('crm_client_id')->nullable()->after('id');

                $table->foreign('crm_client_id')
                    ->references('id')
                    ->on('crm_clients')
                    ->nullOnDelete();

                $table->index('crm_client_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('api_clients', function (Blueprint $table) {
            if (Schema::hasColumn('api_clients', 'crm_client_id')) {
                $table->dropForeign(['crm_client_id']);
                $table->dropIndex(['crm_client_id']);
                $table->dropColumn('crm_client_id');
            }
        });
    }
};