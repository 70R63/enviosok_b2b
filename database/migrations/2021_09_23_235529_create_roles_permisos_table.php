<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRolesPermisosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('roles_permisos', function (Blueprint $table) {
           $table->foreignId('roles_id')->constrained('roles')->onDelete('cascade');
           $table->foreignId('permisos_id')->constrained('permisos')->onDelete('cascade');

           $table->primary(['roles_id','permisos_id']);
        });

        Artisan::call('db:seed', [
            '--class' => 'rolesPermisosSeeder',
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
        Schema::dropIfExists('roles_permisos');
    }
}
