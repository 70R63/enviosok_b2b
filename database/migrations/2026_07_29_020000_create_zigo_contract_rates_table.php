<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateZigoContractRatesTable extends Migration
{
    public function up()
    {
        Schema::create(
            'zigo_contract_rates',
            function (Blueprint $table) {
                $table->id();

                $table->string(
                    'name',
                    191
                );

                $table->string(
                    'carrier',
                    50
                );

                $table->string(
                    'service_code',
                    80
                );

                $table->string(
                    'service_name',
                    120
                );

                $table->decimal(
                    'included_weight_kg',
                    10,
                    3
                );

                $table->decimal(
                    'base_price',
                    12,
                    2
                );

                $table->decimal(
                    'additional_weight_unit_kg',
                    10,
                    3
                )->default(1);

                $table->decimal(
                    'additional_weight_price',
                    12,
                    2
                );

                $table->decimal(
                    'tax_percentage',
                    5,
                    2
                )->default(16);

                $table->string(
                    'currency',
                    3
                )->default('MXN');

                $table->string(
                    'source',
                    100
                )->nullable();

                $table->date(
                    'valid_from'
                )->nullable();

                $table->date(
                    'valid_to'
                )->nullable();

                $table->boolean(
                    'active'
                )->default(true);

                $table->text(
                    'notes'
                )->nullable();

                $table->timestamps();

                $table->index(
                    [
                        'carrier',
                        'service_code',
                        'active',
                    ],
                    'zigo_contract_rate_lookup_idx'
                );

                $table->index(
                    [
                        'valid_from',
                        'valid_to',
                    ],
                    'zigo_contract_rate_validity_idx'
                );
            }
        );
    }

    public function down()
    {
        Schema::dropIfExists(
            'zigo_contract_rates'
        );
    }
}
