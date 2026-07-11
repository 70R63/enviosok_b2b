<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zigo_postal_codes', function (Blueprint $table) {
            $table->id();

            $table->string('codigo_postal', 10)->index();
            $table->string('estado', 120);
            $table->string('municipio', 120);
            $table->string('ciudad', 120)->nullable();

            $table->string('asentamiento', 180);
            $table->string('tipo_asentamiento', 80)->nullable();
            $table->string('zona', 80)->nullable();

            $table->boolean('cobertura_estafeta')->default(true);
            $table->boolean('activo')->default(true);

            $table->timestamps();

            $table->index(['codigo_postal', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zigo_postal_codes');
    }
};
