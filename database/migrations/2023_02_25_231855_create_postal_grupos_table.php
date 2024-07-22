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
        Schema::create('postal_grupos', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->unsignedMediumInteger('cp_inicial')->nullable(false)->default('00000');
            $table->unsignedMediumInteger('cp_final')->nullable(false)->default('00000');
            $table->string('grupo', 2)->nullable(false)->default('A');
            $table->string('entidad_federativa', 50)->nullable(false)->default('entidad_federativa');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('postal_grupos');
    }
};
