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
    Schema::create('b2c_incidencias', function (Blueprint $table) {
        $table->id();

        $table->string('folio')->unique();

        $table->foreignId('user_id')
            ->nullable()
            ->constrained()
            ->nullOnDelete();

        $table->foreignId('cotizacion_id')
            ->nullable()
            ->constrained('b2c_cotizaciones')
            ->nullOnDelete();

        $table->string('tracking_number')->nullable();

        $table->string('tipo');
        $table->string('asunto');
        $table->text('descripcion');

        $table->string('evidencia')->nullable();

        $table->string('estatus')->default('ABIERTA');
        $table->string('prioridad')->default('MEDIA');

        $table->text('respuesta_admin')->nullable();
        $table->timestamp('respondida_at')->nullable();
        $table->unsignedBigInteger('respondida_por')->nullable();

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
        Schema::dropIfExists('b2c_incidencias');
    }
};
