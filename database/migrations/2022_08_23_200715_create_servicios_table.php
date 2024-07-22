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
        Schema::create('servicios', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->boolean('estatus')->default(1);
            
            $table->string('nombre', 50)->default('Servicio o garantia');
            $table->string('descripcion', 100)->default('Descripcion del servicio y/o garantia');
            $table->unsignedInteger('prioridad')->default('1');

        });

        Artisan::call('db:seed', [
            '--class' => 'ServicioSeeder',
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
        Schema::dropIfExists('servicios');
    }
};
