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
        Schema::create('empresa_empresas', function (Blueprint $table) {
            
            $table->unsignedBigInteger('id');
            $table->unsignedBigInteger('empresa_id');

            $table->foreign('id')->references('id')->on('empresas')->onDelete('cascade');
            $table->foreign('empresa_id')->references('id')->on('empresas')->onDelete('cascade');

            $table->primary(['id','empresa_id']);

        });

      

        Artisan::call('db:seed', [
            '--class' => 'EmpresaEmpresasSeeder',
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
        Schema::dropIfExists('empresa_empresas');
    }
};
