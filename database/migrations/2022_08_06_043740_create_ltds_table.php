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
        Schema::create('ltds', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->boolean('estatus')->default(1);
            $table->string('nombre', 100);
            $table->unique(['nombre']);
            $table->string('responsable_legal',50);
            $table->string('email',100);

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ltds');
    }
};
