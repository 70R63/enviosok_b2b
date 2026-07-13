<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateZigoPricingAdjustmentsTable extends Migration
{
    public function up()
    {
        Schema::create('zigo_pricing_adjustments', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('carrier')->default('ESTAFETA');

            $table->string('customer_segment'); 
            // anonymous, b2c, b2b, api, all

            $table->string('package_type')->default('all');
            // sobre, caja, all

            $table->string('adjustment_type');
            // surcharge_percentage, surcharge_fixed, discount_percentage, discount_fixed

            $table->decimal('adjustment_value', 10, 2)->default(0);

            $table->integer('max_uses')->nullable();
            $table->integer('used_count')->default(0);

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['carrier', 'customer_segment', 'package_type', 'active'], 'zpa_lookup_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('zigo_pricing_adjustments');
    }
}