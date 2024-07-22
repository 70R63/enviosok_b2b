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
        Schema::create('cps', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->string('cp', 5)->nullable(false)->default('00000');
            $table->string('colonia', 100)->nullable(false)->default('colonia');
            $table->string('municipio', 100)->nullable(false)->default('municipio');
            $table->string('entidad_federativa', 100)->nullable(false)->default('estado');
            $table->string('tipo_asentamiento', 50)->nullable(false)->default('asentamiento');
            $table->boolean('estatus')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cps');
    }
};
