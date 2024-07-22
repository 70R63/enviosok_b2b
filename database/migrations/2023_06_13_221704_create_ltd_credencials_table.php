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
        Schema::create('ltd_credencials', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->boolean('estatus')->default(1);
            $table->unsignedInteger('empresa_id')->default(0)->index();
            $table->unsignedInteger('ltd_id')->default(0)->index();
            $table->string('recurso', 100)->nullable(false)->default('LABEL');

            $table->string('key_id', 100)->nullable(false)->default('NA');
            $table->string('secret', 100)->nullable(false)->default('NA');
            //20230714 Validar si se elimina de esta tabla se usa en ltd_tipo
            $table->string('customer_number', 50)->nullable(false)->default('NA');
            $table->string('organization', 50)->nullable(false)->default('NA');
            //20230714 Validar si se elimina de esta tabla se usa en ltd_tipo
            $table->string('client_id', 50)->nullable(false)->default('NA');
            $table->string('passwd', 100)->nullable(false)->default('NA');
            $table->string('user', 100)->nullable(false)->default('NA');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ltd_credencials');
    }
};
