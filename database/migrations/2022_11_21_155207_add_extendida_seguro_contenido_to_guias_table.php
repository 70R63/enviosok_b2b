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
        Schema::table('guias', function (Blueprint $table) {
            $table->string('extendida',5)->nullable(false)->default("NO");
            $table->boolean('seguro',5)->nullable(false)->default(false);
            $table->string('contenido',50)->nullable(false)->default("Sin descripcion de envio");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('guias', function (Blueprint $table) {
            $table->dropColumn('extendida');
            $table->dropColumn('seguro');
            $table->dropColumn('contenido');
        });
    }
};
