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
        Schema::create('ltd_coberturas', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->unsignedMediumInteger('cp')->nullable(false)->default("000000");
            $table->string('municipio')->nullable(false)->default("MUNICIPIO");
            $table->string('estado')->nullable(false)->default("estado");
            $table->string('siglas')->nullable(false)->default("siglas");
            $table->string('periocidad')->nullable(false)->default("periocidad");
            $table->string('ocurre')->nullable(false)->default("ocurre");
            $table->string('extendida')->nullable(false)->default("extendida");
            $table->string('garantia')->nullable(false)->default("garantia");
            $table->unsignedTinyInteger('ltd_id')->nullable(false)->default("1");


        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ltd_coberturas');
    }
};
