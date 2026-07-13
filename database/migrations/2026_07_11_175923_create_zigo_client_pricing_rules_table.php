<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateZigoClientPricingRulesTable extends Migration
{
    public function up()
    {
        Schema::create('zigo_client_pricing_rules', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('crm_client_id')->nullable();
            $table->unsignedBigInteger('api_client_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();

            $table->string('name');

            $table->string('customer_segment')->nullable();
            // b2b, b2c, api

            $table->string('package_type')->default('all');
            // sobre, caja, all

            $table->string('discount_type');
            // percentage, fixed

            $table->decimal('discount_value', 10, 2)->default(0);

            $table->integer('max_uses')->nullable();
            $table->integer('used_count')->default(0);

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['crm_client_id', 'active'], 'zcpr_crm_idx');
            $table->index(['api_client_id', 'active'], 'zcpr_api_idx');
            $table->index(['user_id', 'active'], 'zcpr_user_idx');
            $table->index(['customer_segment', 'package_type', 'active'], 'zcpr_segment_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('zigo_client_pricing_rules');
    }
}