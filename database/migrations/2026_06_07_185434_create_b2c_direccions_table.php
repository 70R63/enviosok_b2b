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
    Schema::create('b2c_direcciones', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();

        $table->string('tipo')->default('ORIGEN'); // ORIGEN / DESTINO
        $table->string('alias')->nullable();

        $table->string('nombre');
        $table->string('empresa')->nullable();
        $table->string('email')->nullable();
        $table->string('telefono');

        $table->string('calle');
        $table->string('num_ext');
        $table->string('num_int')->nullable();
        $table->string('referencias')->nullable();

        $table->string('cp');
        $table->string('colonia');
        $table->string('ciudad');
        $table->string('estado');

        $table->boolean('principal')->default(false);
        $table->boolean('activo')->default(true);

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
{
    Schema::dropIfExists('b2c_direcciones');
}
};
