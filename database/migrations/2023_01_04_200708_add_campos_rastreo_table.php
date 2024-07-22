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
        Schema::table('guias', function (Blueprint $table) {
            $table->unsignedSmallInteger('rastreo_estatus')->nullable(false)->default(1);
            $table->dateTime('ultima_fecha')->nullable(false)->useCurrent()->useCurrentOnUpdate();
            $table->string('quien_recibio',50)->nullable(false)->default("nombre no registrado");
            $table->unsignedMediumInteger('largo')->nullable(true)->default(0);
            $table->unsignedMediumInteger('ancho')->nullable(true)->default(0);
            $table->unsignedMediumInteger('alto')->nullable(true)->default(0);
            $table->unsignedMediumInteger('rastreo_peso')->nullable(true)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('guias', function (Blueprint $table) {
            $table->dropColumn('rastreo_estatus');
            $table->dropColumn('ultima_fecha');
            $table->dropColumn('quien_recibio');
            $table->dropColumn('largo');
            $table->dropColumn('ancho');
            $table->dropColumn('alto');
            $table->dropColumn('rastreo_peso');
        });
    }
};
