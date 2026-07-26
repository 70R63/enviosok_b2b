<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'b2c_invoice_requests',
            function (Blueprint $table) {
                $table->foreignId('api_billing_request_id')
                    ->nullable()
                    ->after('managed_by_user_id')
                    ->constrained('api_billing_requests')
                    ->nullOnDelete();

                $table->timestamp('api_hub_synced_at')
                    ->nullable()
                    ->after('api_billing_request_id');

                $table->text('api_hub_sync_error')
                    ->nullable()
                    ->after('api_hub_synced_at');

                $table->unique(
                    'api_billing_request_id',
                    'uq_b2c_invoice_api_billing'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'b2c_invoice_requests',
            function (Blueprint $table) {
                $table->dropUnique(
                    'uq_b2c_invoice_api_billing'
                );
                $table->dropForeign([
                    'api_billing_request_id',
                ]);
                $table->dropColumn([
                    'api_billing_request_id',
                    'api_hub_synced_at',
                    'api_hub_sync_error',
                ]);
            }
        );
    }
};
