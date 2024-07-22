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
        Schema::table('empresas', function (Blueprint $table) {
            
            $table->float('descuento', 6,2)->default(0);
            $table->float('fsc', 6,2)->default(0);
            $table->float('area_extendida', 6,2)->default(0);
            $table->float('precio_mulitpieza', 6,2)->default(0);
            $table->float('premium10', 6,2)->default(0);
            $table->float('premium12', 6,2)->default(0);
            
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('descuento');
            $table->dropColumn('fsc');
            $table->dropColumn('area_extendida');
            $table->dropColumn('precio_mulitpieza');
            $table->dropColumn('premium10');
            $table->dropColumn('premium12');
        });
    }
};
