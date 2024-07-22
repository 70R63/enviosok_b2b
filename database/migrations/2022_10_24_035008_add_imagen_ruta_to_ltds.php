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
        Schema::table('ltds', function (Blueprint $table) {
            $table->string('imagen_ruta',100)->default("sin ruta");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ltds', function (Blueprint $table) {
            $table->dropColumn('imagen_ruta');
        });
    }
};
