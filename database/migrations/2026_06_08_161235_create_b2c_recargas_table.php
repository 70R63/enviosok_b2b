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
    public function up(): void
{
    Schema::create('b2c_recargas', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');

        $table->decimal('monto', 12, 2);
        $table->string('estatus')->default('PENDIENTE');

        $table->string('mp_preference_id')->nullable();
        $table->string('mp_payment_id')->nullable();
        $table->string('mp_status')->nullable();
        $table->string('referencia')->nullable();

        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('b2c_recargas');
}

};
