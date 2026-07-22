<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table(
            'b2c_cotizaciones',
            function (Blueprint $table) {
                $table
                    ->decimal('peso_real', 10, 2)
                    ->nullable()
                    ->after('peso');

                $table
                    ->decimal('peso_volumetrico', 10, 2)
                    ->nullable()
                    ->after('peso_real');

                $table
                    ->decimal('peso_facturable', 10, 2)
                    ->nullable()
                    ->after('peso_volumetrico');
            }
        );
    }

    public function down()
    {
        Schema::table(
            'b2c_cotizaciones',
            function (Blueprint $table) {
                $table->dropColumn([
                    'peso_real',
                    'peso_volumetrico',
                    'peso_facturable',
                ]);
            }
        );
    }
};