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
        Schema::create('reportes_tipos', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->string('nombre', 50)->nullable(true)->default('Nombre');
            $table->string('descripcion', 50)->nullable(true)->default('Descripcion');


        });


        Artisan::call('db:seed', [
            '--class' => 'ReportesTipoSeeder',
            '--force' => true 
        ]);

    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('reportes_tipos');
    }
};
