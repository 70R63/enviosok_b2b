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
        Schema::create('sucursals', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->boolean('estatus')->default(1);

            $table->string('contacto', 50)->nullable(false)->default('contactName');
            $table->string('nombre', 50)->nullable(false)->default('corporateName');
            $table->string('direccion', 100)->nullable(false)->default('address1');
            $table->string('direccion2', 100)->nullable(true)->default('address2');
            $table->string('cp', 5)->nullable(false)->default('00000');
            $table->string('colonia', 100)->nullable(false)->default('neighborhood');
            $table->string('ciudad', 100)->nullable(false)->default('city');
            $table->string('entidad_federativa', 100)->nullable(false)->default('state');
            $table->string('celular', 10)->nullable(false)->default('5512345678');
            $table->string('telefono', 10)->nullable(true)->default('5587654321');
            
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sucursals');
    }
};
