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
        Schema::table('masivas', function (Blueprint $table) {
            $table->string('ruta_zip')->nullable(false)->default("default");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('masivas', function (Blueprint $table) {
            $table->dropColumn('ruta_zip');
        });
    }
};
