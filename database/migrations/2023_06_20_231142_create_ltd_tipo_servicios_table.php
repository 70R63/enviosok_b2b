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
        Schema::create('ltd_tipo_servicios', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->boolean('estatus')->default(1);
            $table->unsignedInteger('empresa_id')->default(0)->index();
            $table->unsignedInteger('ltd_id')->default(0)->index();
            $table->string('service_id_ltd', 50)->nullable(false)->default('70');
            $table->string('service_id', 10)->nullable(false)->default('1');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ltd_tipo_servicios');
    }
};
