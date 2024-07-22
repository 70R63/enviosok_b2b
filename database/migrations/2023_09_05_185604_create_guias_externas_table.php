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
        Schema::create('guias_externas', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->boolean('estatus')->default(1);

            $table->unsignedInteger('user_id')->nullable(true)->default(1);
            $table->unsignedInteger('no_guias')->nullable(true)->default(1);
            $table->float('importe_total',10,2)->nullable(false)->default(0);
            $table->string('file_nombre')->nullable(true)->default("default");

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('guias_externas');
    }
};
