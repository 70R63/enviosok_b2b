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
        Schema::create('dhl_tarifas', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->unsignedInteger('kg')->default(0)->index();
            $table->string('zona', 1)->nullable(false)->default('A')->index();
            $table->float('precio', 6,2)->default(0);
            $table->unsignedInteger('servicio_id')->default(2)->index();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dhl_tarifas');
    }
};
