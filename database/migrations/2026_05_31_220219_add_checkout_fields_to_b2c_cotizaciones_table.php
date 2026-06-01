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
    Schema::table('b2c_cotizaciones', function (Blueprint $table) {
        $table->string('remitente_nombre')->nullable();
        $table->string('remitente_telefono')->nullable();
        $table->string('remitente_email')->nullable();
        $table->string('remitente_direccion')->nullable();

        $table->string('destinatario_nombre')->nullable();
        $table->string('destinatario_telefono')->nullable();
        $table->string('destinatario_email')->nullable();
        $table->string('destinatario_direccion')->nullable();

        $table->string('contenido')->nullable();
        $table->decimal('valor_declarado', 10, 2)->default(0);
        $table->string('referencia')->nullable();
    });
}

public function down()
{
    Schema::table('b2c_cotizaciones', function (Blueprint $table) {
        $table->dropColumn([
            'remitente_nombre',
            'remitente_telefono',
            'remitente_email',
            'remitente_direccion',
            'destinatario_nombre',
            'destinatario_telefono',
            'destinatario_email',
            'destinatario_direccion',
            'contenido',
            'valor_declarado',
            'referencia',
        ]);
    });
}
};
