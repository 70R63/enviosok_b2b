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
        Schema::create('ajustes', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->boolean('estatus')->default(1);

            $table->string('factura_id', 100)->nullable(false)->default('Factura Referencia');

            $table->date('fecha_deposito')->nullable(false)->default("1999-12-31");

            $table->float('importe',10,2)->nullable(false)->default(0);

            $table->string('comentarios', 100)->nullable(true)->default('Comentarios Default');

            $table->unsignedInteger('user_id')->nullable(false)->default(1);
            $table->string('nota_de', 50)->nullable(false)->default('Default');
            $table->unsignedInteger('empresa_id')->nullable(false)->default(1)->index();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ajustes');
    }
};
