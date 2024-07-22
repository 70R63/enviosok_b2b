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
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->boolean('estatus')->default(1);

            $table->unsignedInteger('empresa_id')->nullable(false)->default(1)->index();
            $table->unsignedInteger('usuario_id')->nullable(false)->default(1)->index();
            $table->unsignedInteger('banco_id')->nullable(false)->default(1)->index();
            $table->unsignedInteger('tipo_pago_id')->nullable(false)->default(1)->index();
            $table->string('referencia', 100)->nullable(false)->default('referencia');
            $table->float('importe', 10,4)->nullable(false)->default(0.0);
            $table->date('fecha_deposito')->nullable(false)->default("1999-12-31");
            $table->string('hora_deposito', 10)->nullable(false)->default('00:00');

            $table->unique(['banco_id', 'referencia']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pagos');
    }
};
