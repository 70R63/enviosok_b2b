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
        Schema::create('domicilios', function (Blueprint $table) {
            $table->id()->comment('Identificador único');
            $table->string('cp',5)->nullable()->comment('Código postal');
            $table->string('estado',255)->nullable()->comment('Estado de la república');
            $table->string('codigo_estado',255)->nullable()->comment('Código del estado');
            $table->string('tipo_asentamiento',255)->nullable()->comment('Tipo de asentamiento');
            $table->unsignedBigInteger('tipo_asentamiento_id')->nullable()->comment('Id del tipo de asentamiento');
            $table->unsignedBigInteger('tipo_vialidad_id')->nullable()->comment('Id del tipo de vialidad');
            $table->string('municipio_alcaldia',255)->nullable()->comment('Municipio');
            $table->string('colonia',255)->nullable()->comment('Colonia');
            $table->string('calle',255)->nullable()->comment('Calle');
            $table->string('no_exterior',255)->nullable()->comment('Número exterior');
            $table->string('no_interior',255)->nullable()->comment('Número interior');
            $table->text('lugar')->nullable()->comment('Domicilio');
            $table->text('referencias')->nullable()->comment('Referencia del domicilio');
            $table->string('latitud',255)->nullable()->comment('Latitud');
            $table->string('longitud',255)->nullable()->comment('Longitud');

            $table->morphs('modelo');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('domicilios');
    }
};
