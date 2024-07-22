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
        Schema::create('reportes', function (Blueprint $table) {
            $table->id();
            $table->timestamps();


            $table->integer('cia')->nullable(true)->default(1);
            $table->integer('ltd_id')->nullable(true)->default(0);
            $table->integer('servicio_id')->nullable(true)->default(0);
            $table->integer('registros_cantidad')->nullable(true)->default(0);
            $table->date('fecha_ini')->nullable(true)->default("0000-00-00");
            $table->date('fecha_fin')->nullable(true)->default("0000-00-00");
            $table->string('ruta_csv', 50)->nullable(true)->default('ruta_default');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('reportes');
    }
};
