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
    Schema::table('b2c_cotizaciones', function (Blueprint $table) {
        $table->string('remitente_num_ext')->nullable()->after('remitente_direccion');
        $table->string('remitente_num_int')->nullable()->after('remitente_num_ext');
        $table->string('ciudad_origen')->nullable()->after('remitente_num_int');
        $table->string('estado_origen')->nullable()->after('ciudad_origen');

        $table->string('destinatario_num_ext')->nullable()->after('destinatario_direccion');
        $table->string('destinatario_num_int')->nullable()->after('destinatario_num_ext');
        $table->string('ciudad_destino')->nullable()->after('destinatario_num_int');
        $table->string('estado_destino')->nullable()->after('ciudad_destino');
    });
}

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
{
    Schema::table('b2c_cotizaciones', function (Blueprint $table) {
        $table->dropColumn([
            'remitente_num_ext',
            'remitente_num_int',
            'ciudad_origen',
            'estado_origen',
            'destinatario_num_ext',
            'destinatario_num_int',
            'ciudad_destino',
            'estado_destino',
        ]);
    });
}
};
