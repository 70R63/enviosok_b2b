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
        Schema::create('tarifas', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->boolean('estatus')->default(1);
            
            $table->unsignedInteger('ltds_id')->default(0);
            $table->unsignedInteger('servicio_id')->default(0);
            $table->unsignedInteger('kg_ini')->default(1);
            $table->unsignedInteger('kg_fin')->default(1);
            $table->unsignedInteger('kg_extra')->default(1);
            $table->unsignedInteger('extendida')->default(1);
            $table->unsignedInteger('costo')->default(1);
            $table->timestamp('fecha')->useCurrent(); 

        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tarifas');
    }
};
