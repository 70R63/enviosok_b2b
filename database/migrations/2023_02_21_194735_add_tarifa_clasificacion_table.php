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
        Schema::table('empresa_ltds', function (Blueprint $table) {
            $table->unsignedTinyInteger('tarifa_clasificacion')->nullable(false)->default(1);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('empresa_ltds', function (Blueprint $table) {
            $table->dropColumn('tarifa_clasificacion');
        });
    }
};
