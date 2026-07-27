<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('b2c_cotizaciones', function (Blueprint $table) {
            $table->string('guia_provider_reference', 100)
                ->nullable()
                ->index()
                ->after('guia_estatus');

            $table->unsignedBigInteger('guia_provider_request_number')
                ->nullable()
                ->unique()
                ->after('guia_provider_reference');

            $table->unsignedInteger('guia_generation_attempts')
                ->default(0)
                ->after('guia_provider_request_number');

            $table->timestamp('guia_generation_started_at')
                ->nullable()
                ->after('guia_generation_attempts');

            $table->timestamp('guia_last_attempt_at')
                ->nullable()
                ->after('guia_generation_started_at');

            $table->timestamp('guia_generated_at')
                ->nullable()
                ->after('guia_last_attempt_at');

            $table->timestamp('guia_recovered_at')
                ->nullable()
                ->after('guia_generated_at');

            $table->string('guia_last_error_code', 80)
                ->nullable()
                ->after('guia_recovered_at');

            $table->text('guia_last_error_message')
                ->nullable()
                ->after('guia_last_error_code');

            $table->json('guia_request_snapshot')
                ->nullable()
                ->after('guia_last_error_message');

            $table->json('guia_response_snapshot')
                ->nullable()
                ->after('guia_request_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('b2c_cotizaciones', function (Blueprint $table) {
            $table->dropUnique(
                'b2c_cotizaciones_guia_provider_request_number_unique'
            );

            $table->dropIndex(
                'b2c_cotizaciones_guia_provider_reference_index'
            );

            $table->dropColumn([
                'guia_provider_reference',
                'guia_provider_request_number',
                'guia_generation_attempts',
                'guia_generation_started_at',
                'guia_last_attempt_at',
                'guia_generated_at',
                'guia_recovered_at',
                'guia_last_error_code',
                'guia_last_error_message',
                'guia_request_snapshot',
                'guia_response_snapshot',
            ]);
        });
    }
};
