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
        Schema::create('ltd_sesions', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->unsignedBigInteger('empresa_id');
            $table->mediumText('token');
            $table->unsignedBigInteger('ltd_id')->nullable(false)->default(0);
            $table->dateTime('expira_en')->nullable(false)->default("2022-10-01 00:00:00");

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ltd_sesions');
    }
};
