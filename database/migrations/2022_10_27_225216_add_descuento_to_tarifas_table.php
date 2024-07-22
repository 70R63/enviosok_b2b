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
        Schema::table('tarifas', function (Blueprint $table) {
            $table->unsignedMediumInteger('descuento')->nullable(false)->default(0);
            $table->unsignedMediumInteger('seguro')->nullable(false)->default(0);
            $table->unsignedMediumInteger('exceso_dimension')->nullable(false)->default(0);
            
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tarifas', function (Blueprint $table) {
            $table->dropColumn('descuento');
            $table->dropColumn('seguro');
            $table->dropColumn('exceso_dimension');
        });
    }
};
