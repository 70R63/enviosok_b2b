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
        Schema::create('b2c_movimientos_saldo', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');

            $table->string('tipo');

            $table->decimal('monto', 12, 2);

            $table->decimal('saldo_anterior', 12, 2);

            $table->decimal('saldo_nuevo', 12, 2);

            $table->string('referencia')->nullable();

            $table->string('estatus')->default('APLICADO');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('b2c_movimientos_saldo');
    }
};
