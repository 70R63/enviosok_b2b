<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'b2c_checkout_debt_allocations',
            function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger(
                    'checkout_cotizacion_id'
                );
                $table->unsignedBigInteger('adeudo_id')->unique();
                $table->unsignedBigInteger('user_id');
                $table->decimal('monto', 12, 2);
                $table->string('estatus', 30)
                    ->default('RESERVADO');
                $table->string('payment_method', 40)
                    ->nullable();
                $table->string('payment_reference', 191)
                    ->nullable();
                $table->timestamp('reserved_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('released_at')->nullable();
                $table->timestamps();

                $table->index(
                    ['checkout_cotizacion_id', 'estatus'],
                    'b2c_checkout_debt_quote_status_idx'
                );
                $table->index(
                    ['user_id', 'estatus'],
                    'b2c_checkout_debt_user_status_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'b2c_checkout_debt_allocations'
        );
    }
};
