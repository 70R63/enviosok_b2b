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
            $table->unsignedTinyInteger('tipo_pago_id')->nullable(false)->default(2);
            $table->float('limite_credito',9,2)->nullable(true)->default(0);
            $table->unsignedTinyInteger('plazo_credito_id')->nullable(true)->default(0);
            
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
            $table->dropColumn('tipo_pago_id');
            $table->dropColumn('limite_credito');
            $table->dropColumn('plazo_credito_id');
        });
    }
};
