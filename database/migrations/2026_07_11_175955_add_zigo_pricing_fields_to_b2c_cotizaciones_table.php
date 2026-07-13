<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddZigoPricingFieldsToB2cCotizacionesTable extends Migration
{
    public function up()
    {
        Schema::table('b2c_cotizaciones', function (Blueprint $table) {
            if (!Schema::hasColumn('b2c_cotizaciones', 'provider_base_price')) {
                $table->decimal('provider_base_price', 10, 2)->nullable()->after('precio');
            }

            if (!Schema::hasColumn('b2c_cotizaciones', 'zigo_margin_percentage')) {
                $table->decimal('zigo_margin_percentage', 8, 2)->nullable()->after('provider_base_price');
            }

            if (!Schema::hasColumn('b2c_cotizaciones', 'zigo_fixed_fee')) {
                $table->decimal('zigo_fixed_fee', 10, 2)->nullable()->after('zigo_margin_percentage');
            }

            if (!Schema::hasColumn('b2c_cotizaciones', 'zigo_margin_amount')) {
                $table->decimal('zigo_margin_amount', 10, 2)->nullable()->after('zigo_fixed_fee');
            }

            if (!Schema::hasColumn('b2c_cotizaciones', 'zigo_adjustment_type')) {
                $table->string('zigo_adjustment_type')->nullable()->after('zigo_margin_amount');
            }

            if (!Schema::hasColumn('b2c_cotizaciones', 'zigo_adjustment_value')) {
                $table->decimal('zigo_adjustment_value', 10, 2)->nullable()->after('zigo_adjustment_type');
            }

            if (!Schema::hasColumn('b2c_cotizaciones', 'zigo_adjustment_amount')) {
                $table->decimal('zigo_adjustment_amount', 10, 2)->nullable()->after('zigo_adjustment_value');
            }

            if (!Schema::hasColumn('b2c_cotizaciones', 'zigo_discount_type')) {
                $table->string('zigo_discount_type')->nullable()->after('zigo_adjustment_amount');
            }

            if (!Schema::hasColumn('b2c_cotizaciones', 'zigo_discount_value')) {
                $table->decimal('zigo_discount_value', 10, 2)->nullable()->after('zigo_discount_type');
            }

            if (!Schema::hasColumn('b2c_cotizaciones', 'zigo_discount_amount')) {
                $table->decimal('zigo_discount_amount', 10, 2)->nullable()->after('zigo_discount_value');
            }

            if (!Schema::hasColumn('b2c_cotizaciones', 'zigo_final_price')) {
                $table->decimal('zigo_final_price', 10, 2)->nullable()->after('zigo_discount_amount');
            }

            if (!Schema::hasColumn('b2c_cotizaciones', 'zigo_profit_amount')) {
                $table->decimal('zigo_profit_amount', 10, 2)->nullable()->after('zigo_final_price');
            }

            if (!Schema::hasColumn('b2c_cotizaciones', 'zigo_customer_segment')) {
                $table->string('zigo_customer_segment')->nullable()->after('zigo_profit_amount');
            }

            if (!Schema::hasColumn('b2c_cotizaciones', 'zigo_pricing_rule_id')) {
                $table->unsignedBigInteger('zigo_pricing_rule_id')->nullable()->after('zigo_customer_segment');
            }

            if (!Schema::hasColumn('b2c_cotizaciones', 'zigo_pricing_adjustment_id')) {
                $table->unsignedBigInteger('zigo_pricing_adjustment_id')->nullable()->after('zigo_pricing_rule_id');
            }

            if (!Schema::hasColumn('b2c_cotizaciones', 'zigo_client_pricing_rule_id')) {
                $table->unsignedBigInteger('zigo_client_pricing_rule_id')->nullable()->after('zigo_pricing_adjustment_id');
            }
        });
    }

    public function down()
    {
        Schema::table('b2c_cotizaciones', function (Blueprint $table) {
            $columns = [
                'provider_base_price',
                'zigo_margin_percentage',
                'zigo_fixed_fee',
                'zigo_margin_amount',
                'zigo_adjustment_type',
                'zigo_adjustment_value',
                'zigo_adjustment_amount',
                'zigo_discount_type',
                'zigo_discount_value',
                'zigo_discount_amount',
                'zigo_final_price',
                'zigo_profit_amount',
                'zigo_customer_segment',
                'zigo_pricing_rule_id',
                'zigo_pricing_adjustment_id',
                'zigo_client_pricing_rule_id',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('b2c_cotizaciones', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}