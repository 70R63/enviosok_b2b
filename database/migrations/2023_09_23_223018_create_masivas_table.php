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
        Schema::create('masivas', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->boolean('estatus')->default(1);

            $table->unsignedInteger('user_id')->nullable(false)->default(1);
            $table->unsignedInteger('no_registros')->nullable(false)->default(0);
            $table->string('archivo_nombre')->nullable(false)->default("default");
            $table->unsignedInteger('no_registros_fallo')->nullable(false)->default(0);
            $table->string('archivo_fallo',150)->nullable(false)->default("default");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('masivas');
    }
};
