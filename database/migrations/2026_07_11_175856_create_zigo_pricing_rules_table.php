<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateZigoPricingRulesTable extends Migration
{
    public function up()
    {
        Schema::create('zigo_pricing_rules', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('carrier')->default('ESTAFETA');

            $table->string('customer_segment');
            // anonymous, b2c, b2b, api

            $table->string('plan')->nullable();
            // STARTER, BUSINESS, ENTERPRISE o null

            $table->string('package_type')->default('all');
            // sobre, caja, all

            $table->decimal('margin_percentage', 8, 2)->default(0);
            $table->decimal('fixed_fee', 10, 2)->default(0);
            $table->decimal('min_price', 10, 2)->nullable();

            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(
                ['carrier', 'customer_segment', 'package_type', 'active'],
                'zpr_lookup_idx'
            );
        });
    }

    public function down()
    {
        Schema::dropIfExists('zigo_pricing_rules');
    }
}
