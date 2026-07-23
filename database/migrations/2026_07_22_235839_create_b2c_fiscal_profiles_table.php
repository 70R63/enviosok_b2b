<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'b2c_fiscal_profiles',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('user_id')
                    ->unique()
                    ->constrained()
                    ->cascadeOnDelete();

                $table->string('razon_social', 255);

                $table->string('rfc', 13)
                    ->index();

                $table->string(
                    'codigo_postal_fiscal',
                    5
                );

                $table->string(
                    'direccion_fiscal',
                    500
                )->nullable();

                $table->string(
                    'regimen_fiscal',
                    3
                );

                $table->string(
                    'uso_cfdi',
                    4
                );

                $table->string(
                    'email_facturacion',
                    255
                );

                $table->boolean('activo')
                    ->default(true);

                $table->timestamps();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'b2c_fiscal_profiles'
        );
    }
};