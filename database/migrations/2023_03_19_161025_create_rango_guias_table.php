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
        Schema::create('rango_guias', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->unsignedInteger('inicial')->default(1);
            $table->unsignedInteger('final')->default(1);
            $table->unsignedInteger('actual')->default(1);
            
            $table->unsignedInteger('ltd_id')->default(0)->index();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('rango_guias');
    }
};
