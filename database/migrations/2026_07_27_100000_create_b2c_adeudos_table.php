<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('b2c_adeudos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('cotizacion_id')
                ->constrained('b2c_cotizaciones')
                ->cascadeOnDelete();

            // La tabla guias es heredada; se conserva el vínculo sin FK.
            $table->unsignedBigInteger('guia_id')->nullable()->index();
            $table->string('tracking_number', 100)->nullable()->index();

            $table->string('tipo', 30)->default('REPESAJE');
            $table->string('concepto', 255);

            $table->decimal('peso_cotizado', 10, 2)->nullable();
            $table->decimal('peso_real', 10, 2)->nullable();
            $table->decimal('costo_cotizado', 12, 2);
            $table->decimal('costo_real', 12, 2);
            $table->decimal('monto', 12, 2);

            $table->string('referencia_xperta', 150);
            $table->text('observaciones')->nullable();
            $table->string('evidencia_path', 500)->nullable();

            $table->string('estatus', 30)
                ->default('PENDIENTE')
                ->index();

            $table->string('payment_method', 50)->nullable();
            $table->string('payment_id', 150)->nullable()->index();
            $table->string(
                'payment_external_reference',
                150
            )->nullable()->index();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('cancelled_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('cancel_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['cotizacion_id', 'referencia_xperta'],
                'b2c_adeudos_cotizacion_referencia_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('b2c_adeudos');
    }
};
