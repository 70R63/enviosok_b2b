<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'zigo_provider_quote_observations',
            function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger(
                    'agreement_service_id'
                );
                $table->unsignedBigInteger(
                    'b2c_cotizacion_id'
                )->nullable();
                $table->string(
                    'provider_source_code',
                    50
                );
                $table->string('service_code', 80);
                $table->string('origin_zip', 5);
                $table->string('destination_zip', 5);
                $table->decimal('weight_kg', 10, 3);
                $table->decimal('length_cm', 10, 3);
                $table->decimal('width_cm', 10, 3);
                $table->decimal('height_cm', 10, 3);
                $table->string('currency', 3)->default('MXN');
                $table->decimal(
                    'total',
                    12,
                    2
                )->nullable();
                $table->decimal(
                    'extended_area_amount',
                    12,
                    2
                )->default(0);
                $table->boolean('success')->default(true);
                $table->text('error_message')->nullable();
                $table->json('response_payload')->nullable();
                $table->timestamp('observed_at');
                $table->timestamps();

                $table->foreign(
                    'agreement_service_id',
                    'zpqobs_agreement_service_fk'
                )
                    ->references('id')
                    ->on('zigo_agreement_services')
                    ->cascadeOnDelete();

                $table->index(
                    [
                        'agreement_service_id',
                        'observed_at',
                    ],
                    'zpqobs_latest_lookup_idx'
                );

                $table->index(
                    [
                        'provider_source_code',
                        'service_code',
                    ],
                    'zpqobs_source_service_idx'
                );

                $table->index(
                    [
                        'success',
                        'observed_at',
                    ],
                    'zpqobs_status_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'zigo_provider_quote_observations'
        );
    }
};
