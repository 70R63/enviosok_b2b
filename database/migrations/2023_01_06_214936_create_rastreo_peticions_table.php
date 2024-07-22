<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use Carbon\Carbon;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('rastreo_peticions', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('usuario',50)->nullable(false)->default("usuario Default");
            $table->dateTime('peticion_ini')->nullable(false)->useCurrent();
            $table->dateTime('peticion_fin')->nullable(false)->default(Carbon::now()->toDateTimeString());
            $table->boolean('completado')->default(0);

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('rastreo_peticions');
    }
};
