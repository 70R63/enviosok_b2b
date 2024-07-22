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
        Schema::create('reporte_pagos', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->boolean('estatus')->default(1);

            
            $table->unsignedInteger('user_id')->nullable(false)->default(1);
            $table->unsignedInteger('empresa_id')->nullable(false)->default(1);
            $table->unsignedInteger('banco_id')->nullable(false)->default(0);

            $table->date('fecha_ini')->nullable(false)->default("1999-12-31");
            $table->date('fecha_fin')->nullable(false)->default("1999-12-31");
            $table->string('ruta_csv', 100)->nullable(false)->default('ruta_default');
            $table->unsignedInteger('registros_cantidad')->nullable(false)->default(0);

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('reporte_pagos');
    }
};
