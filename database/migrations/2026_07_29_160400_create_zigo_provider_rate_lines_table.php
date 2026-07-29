<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zigo_provider_rate_lines', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('rate_card_id');
            $table->string('zone_code', 50)->nullable();
            $table->string('origin_zone', 50)->nullable();
            $table->string('destination_zone', 50)->nullable();
            $table->decimal('min_weight_kg', 10, 3)->nullable();
            $table->decimal('max_weight_kg', 10, 3)->nullable();
            $table->decimal('included_weight_kg', 10, 3)->nullable();
            $table->decimal('base_price', 12, 2);
            $table->decimal('additional_weight_unit_kg', 10, 3)->nullable();
            $table->decimal('additional_weight_price', 12, 2)->default(0);
            $table->decimal('extended_area_price', 12, 2)->default(0);
            $table->decimal('oversize_price', 12, 2)->default(0);
            $table->decimal('insurance_percentage', 7, 4)->default(0);
            $table->decimal('fuel_surcharge_percentage', 7, 4)->default(0);
            $table->decimal('multipiece_price', 12, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(100);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('rate_card_id', 'zprl_card_fk')
                ->references('id')
                ->on('zigo_provider_rate_cards')
                ->cascadeOnDelete();

            $table->index(
                ['rate_card_id', 'active', 'sort_order'],
                'zprl_lookup_idx'
            );

            $table->index(
                ['min_weight_kg', 'max_weight_kg'],
                'zprl_weight_idx'
            );

            $table->index(
                ['origin_zone', 'destination_zone'],
                'zprl_zone_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zigo_provider_rate_lines');
    }
};
