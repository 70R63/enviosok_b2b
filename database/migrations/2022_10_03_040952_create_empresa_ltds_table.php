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
        Schema::create('empresa_ltds', function (Blueprint $table) {
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('ltd_id');
            
            $table->foreign('empresa_id')->references('id')->on('empresas')->onDelete('cascade');
            $table->foreign('ltd_id')->references('id')->on('ltds')->onDelete('cascade');

            $table->primary(['empresa_id','ltd_id']);
            
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('empresa_ltds');
    }
};
