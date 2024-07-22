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
        Schema::create('guias', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedInteger('empresa_id')->nullable(false)->default(1);
            $table->unsignedMediumInteger('estatus')->nullable(false)->default(1);
            $table->string('usuario')->nullable(false)->default("usuario Default");
            $table->unsignedInteger('ltd_id')->nullable(false)->default(0);
            $table->string('cia')->nullable(false)->default("CIA Origin");
            $table->string('cia_d')->nullable(false)->default("CIA Destino");
            $table->unsignedInteger('piezas')->nullable(false)->default(0);

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('guias');
    }
};
