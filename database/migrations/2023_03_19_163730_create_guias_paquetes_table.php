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
        Schema::create('guias_paquetes', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->unsignedInteger('peso')->default(1);
            $table->unsignedInteger('alto')->default(1);
            $table->unsignedInteger('largo')->default(1);
            $table->unsignedInteger('ancho')->default(1);
            $table->unsignedInteger('precio_unitario')->default(1);
            $table->unsignedInteger('empresa_id')->default(0)->index();
            $table->unsignedInteger('guia_id')->default(0)->index();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('guias_paquetes');
    }
};
