<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('b2c_cotizaciones', function (Blueprint $table) {
            $table->string('payment_verification_status', 30)
                ->nullable()
                ->after('payment_collection_id');
            $table->string('payment_verification_source', 30)
                ->nullable()
                ->after('payment_verification_status');
            $table->text('payment_verification_error')
                ->nullable()
                ->after('payment_verification_source');
            $table->timestamp('payment_verification_attempted_at')
                ->nullable()
                ->after('payment_verification_error');
            $table->timestamp('payment_verified_at')
                ->nullable()
                ->after('payment_verification_attempted_at');
            $table->decimal('payment_verified_amount', 12, 2)
                ->nullable()
                ->after('payment_verified_at');
            $table->string('payment_verified_currency', 3)
                ->nullable()
                ->after('payment_verified_amount');
            $table->string(
                'payment_verified_external_reference',
                120
            )
                ->nullable()
                ->after('payment_verified_currency');
            $table->json('payment_verification_payload')
                ->nullable()
                ->after('payment_verified_external_reference');

            $table->index(
                ['payment_verification_status', 'payment_verified_at'],
                'b2c_payment_verification_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('b2c_cotizaciones', function (Blueprint $table) {
            $table->dropIndex(
                'b2c_payment_verification_status_index'
            );
            $table->dropColumn([
                'payment_verification_status',
                'payment_verification_source',
                'payment_verification_error',
                'payment_verification_attempted_at',
                'payment_verified_at',
                'payment_verified_amount',
                'payment_verified_currency',
                'payment_verified_external_reference',
                'payment_verification_payload',
            ]);
        });
    }
};
