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
        Schema::table('ltd_sesions', function (Blueprint $table) {
            $table->unsignedSmallInteger('servicio')->nullable(false)->default(1);

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ltd_sesions', function (Blueprint $table) {
            $table->dropColumn('servicio');
        });
    }
};
