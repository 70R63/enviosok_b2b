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
                $table->foreignId('managed_by_user_id')
                    ->nullable()
                    ->after('fiscal_profile_id')
                    ->constrained('users')
                    ->nullOnDelete();

                $table->text('internal_notes')
                    ->nullable()
                    ->after('error_message');

                $table->text('rejection_reason')
                    ->nullable()
                    ->after('internal_notes');

                $table->text('cancellation_reason')
                    ->nullable()
                    ->after('rejection_reason');

                $table->timestamp('attended_at')
                    ->nullable()
                    ->after('solicitada_at');

                $table->timestamp('rejected_at')
                    ->nullable()
                    ->after('facturada_at');

                $table->timestamp('cancelled_at')
                    ->nullable()
                    ->after('rejected_at');
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'b2c_invoice_requests',
            function (Blueprint $table) {
                $table->dropForeign([
                    'managed_by_user_id',
                ]);

                $table->dropColumn([
                    'managed_by_user_id',
                    'internal_notes',
                    'rejection_reason',
                    'cancellation_reason',
                    'attended_at',
                    'rejected_at',
                    'cancelled_at',
                ]);
            }
        );
    }
};
