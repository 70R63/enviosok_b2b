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
        Schema::table('postal_zonas', function (Blueprint $table) {
            $table->unsignedTinyInteger('ltd_id')->default(0)->index();
        });

        Artisan::call('db:seed', [
            '--class' => 'PostalZonasDHLSeeder',
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
        Schema::table('postal_zonas', function (Blueprint $table) {
            $table->dropColumn('ltd_id');
        });
    }
};
