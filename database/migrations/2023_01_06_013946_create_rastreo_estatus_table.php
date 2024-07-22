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
        Schema::create('rastreo_estatus', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('nombre',30);
            $table->boolean('estatus')->default(1);
        });

        Artisan::call('db:seed', [
            '--class' => 'RastreosSeeder',
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
        Schema::dropIfExists('rastreo_estatus');
    }
};
