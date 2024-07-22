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
        Schema::create('plazo_creditos', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->boolean('estatus')->default(1);

            $table->string('nombre', 50)->nullable(false)->default('15 Dias');
            $table->unsignedTinyInteger('dias')->nullable(false)->default(0);
            
        });

        Artisan::call('db:seed', [
            '--class' => 'Database\Seeders\CatalogosSistema\PlazoCreditosSeeder',
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
        Schema::dropIfExists('plazo_creditos');
    }
};
