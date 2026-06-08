<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
{
    Schema::table('b2c_cotizaciones', function (Blueprint $table) {
        $table->unsignedBigInteger('guia_id')->nullable()->after('estatus');
        $table->string('tracking_number')->nullable()->after('guia_id');
        $table->string('documento')->nullable()->after('tracking_number');
        $table->string('guia_estatus')->nullable()->after('documento');
    });
}

public function down()
{
    Schema::table('b2c_cotizaciones', function (Blueprint $table) {
        $table->dropColumn([
            'guia_id',
            'tracking_number',
            'documento',
            'guia_estatus',
        ]);
    });
}
};
