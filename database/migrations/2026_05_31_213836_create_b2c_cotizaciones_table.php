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
    Schema::create('b2c_cotizaciones', function (Blueprint $table) {
        $table->id();
        $table->string('cp_origen', 120);
        $table->string('colonia_origen')->nullable();
        $table->string('cp_destino', 120);
        $table->string('colonia_destino')->nullable();
        $table->string('tipo_envio', 30);
        $table->decimal('peso', 10, 2);
        $table->string('medidas')->nullable();
        $table->string('logistico')->nullable();
        $table->string('servicio')->nullable();
        $table->decimal('precio', 10, 2)->nullable();
        $table->string('estatus')->default('COTIZADA');
        $table->timestamps();
    });
}

public function down()
{
    Schema::dropIfExists('b2c_cotizaciones');
}
};
