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
        $table->string('payment_id')->nullable();
        $table->string('payment_status')->nullable();
        $table->string('payment_external_reference')->nullable();
        $table->string('payment_collection_id')->nullable();
    });
}

public function down()
{
    Schema::table('b2c_cotizaciones', function (Blueprint $table) {
        $table->dropColumn([
            'payment_id',
            'payment_status',
            'payment_external_reference',
            'payment_collection_id',
        ]);
    });
}
};
