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
        Schema::create('sepomex', function (Blueprint $table) {
            $table->string('d_codigo')->comment('Código postal');
            $table->string('d_asenta')->comment('Asentamiento o colonia');
            $table->string('d_tipo_asenta')->comment('Tipo de asentamiento');
            $table->string('d_mnpio')->comment('Municipio');
            $table->string('d_estado')->comment('Estado de la república');
            $table->string('d_ciudad')->nullable()->comment('Ciudad');
            $table->string('d_CP');
            $table->string('c_estado');
            $table->string('c_oficina');
            $table->string('c_CP');
            $table->string('c_tipo_asenta');
            $table->string('c_mnpio');
            $table->string('id_asenta_cpcons');
            $table->string('d_zona');
            $table->string('c_cve_ciudad')->nullable();
            $table->string('codigo_estado')->nullable();

            $table->index('d_codigo');
            $table->index('c_estado');
            $table->index('c_mnpio');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sepomex');
    }
};
