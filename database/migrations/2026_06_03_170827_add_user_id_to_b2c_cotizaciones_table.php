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
    Schema::table('b2c_cotizaciones', function (Blueprint $table) {
        $table->unsignedBigInteger('user_id')->nullable()->after('id');
    });
}

public function down()
{
    Schema::table('b2c_cotizaciones', function (Blueprint $table) {
        $table->dropColumn('user_id');
    });
}
};
