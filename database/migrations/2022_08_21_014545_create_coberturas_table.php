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
        Schema::create('coberturas', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->boolean('estatus')->default(1);
            $table->unsignedInteger('ltds_id')->default(0);
            $table->unsignedInteger('municipio_num')->default(0);
            $table->unsignedInteger('estado_num')->default(0);
            $table->unsignedInteger('cp_num')->default(0);
            $table->unsignedInteger('reexpedicion_num')->default(0);
            $table->unsignedInteger('garantia_num')->default(0);

        });

        Schema::create('coberturas_listas', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->boolean('estatus')->default(1);
            $table->unsignedInteger('ltds_id')->default(0);
            $table->unsignedInteger('coberturas_id')->default(0);
            $table->string('municipio', 100)->default('municipio');
            $table->string('estado', 100)->default('estado');
            $table->string('reexpedicion', 2)->default('NO');
            $table->string('garantia', 50)->default('garantia');      
            $table->unsignedMediumInteger('cp')->default(00000);
            

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('coberturas');
        Schema::dropIfExists('coberturas_listas');
    }
};
