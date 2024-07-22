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
        Schema::table('guias', function (Blueprint $table) {
            $table->float('costo_base', 10,4)->nullable(true)->default(0.0);
            $table->float('costo_kg_extra', 8,2)->nullable(true)->default(0.0);
            $table->float('peso_dimensional', 10,4)->nullable(true)->default(0.0);
            $table->float('peso_bascula', 10,4)->nullable(true)->default(0.0);
            $table->integer('sobre_peso_kg')->nullable(true)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('guias', function (Blueprint $table) {
            $table->dropColumn('costo_base');
            $table->dropColumn('costo_kg_extra');
            $table->dropColumn('peso_dimensional');
            $table->dropColumn('peso_bascula');
            $table->dropColumn('sobre_peso_kg');
        });
    }
};
