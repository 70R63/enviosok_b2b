<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'b2c_saldo_reversals',
            function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger(
                    'cotizacion_id'
                )->index();
                $table->unsignedBigInteger(
                    'user_id'
                )->index();
                $table->unsignedBigInteger(
                    'purchase_movement_id'
                )->unique();
                $table->unsignedBigInteger(
                    'reversal_movement_id'
                )->nullable()->unique();
                $table->unsignedBigInteger(
                    'admin_user_id'
                )->nullable()->index();
                $table->decimal(
                    'amount',
                    12,
                    2
                );
                $table->text('reason');
                $table->boolean(
                    'provider_confirmation'
                )->default(false);
                $table->string(
                    'status',
                    30
                )->default('APPLIED');
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index([
                    'cotizacion_id',
                    'status',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'b2c_saldo_reversals'
        );
    }
};
