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
        Schema::table('ltd_tipo_servicios', function (Blueprint $table) {
            $table->string('sales_organization', 5)->nullable(false)->default('112');
            $table->string('customer_number', 50)->nullable(false)->default('000000');
            $table->string('client_id', 50)->nullable(false)->default('55');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ltd_tipo_servicios', function (Blueprint $table) {
            $table->dropColumn('sales_organization');
            $table->dropColumn('customer_number');
            $table->dropColumn('client_id');

        });
    }
};
