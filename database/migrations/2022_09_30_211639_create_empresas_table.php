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
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->boolean('estatus')->default(1);

            $table->string('contacto', 50)->nullable(false)->default('contactName');
            $table->string('nombre', 50)->nullable(false)->default('corporateName');
            $table->string('email')->nullable(true)->unique();
            $table->string('telefono', 10)->nullable(false)->default('5555555555');

        });

        Artisan::call('db:seed', [
            '--class' => 'EmpresaSeeder',
            '--force' => true 
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('empresas');
    }
};
