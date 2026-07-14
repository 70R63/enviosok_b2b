<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('b2c_cotizaciones', function (Blueprint $table) {
            if (!Schema::hasColumn('b2c_cotizaciones', 'requiere_seguro_envio')) {
                $table->boolean('requiere_seguro_envio')->default(false)->after('valor_declarado');
            }

            if (!Schema::hasColumn('b2c_cotizaciones', 'seguro_porcentaje')) {
                $table->decimal('seguro_porcentaje', 5, 2)->default(2.00)->after('requiere_seguro_envio');
            }

            if (!Schema::hasColumn('b2c_cotizaciones', 'seguro_iva_porcentaje')) {
                $table->decimal('seguro_iva_porcentaje', 5, 2)->default(16.00)->after('seguro_porcentaje');
            }

            if (!Schema::hasColumn('b2c_cotizaciones', 'seguro_monto')) {
                $table->decimal('seguro_monto', 10, 2)->default(0.00)->after('seguro_iva_porcentaje');
            }

            if (!Schema::hasColumn('b2c_cotizaciones', 'precio_sin_seguro')) {
                $table->decimal('precio_sin_seguro', 10, 2)->nullable()->after('seguro_monto');
            }
        });
    }

    public function down(): void
    {
        Schema::table('b2c_cotizaciones', function (Blueprint $table) {
            if (Schema::hasColumn('b2c_cotizaciones', 'precio_sin_seguro')) {
                $table->dropColumn('precio_sin_seguro');
            }

            if (Schema::hasColumn('b2c_cotizaciones', 'seguro_monto')) {
                $table->dropColumn('seguro_monto');
            }

            if (Schema::hasColumn('b2c_cotizaciones', 'seguro_iva_porcentaje')) {
                $table->dropColumn('seguro_iva_porcentaje');
            }

            if (Schema::hasColumn('b2c_cotizaciones', 'seguro_porcentaje')) {
                $table->dropColumn('seguro_porcentaje');
            }

            if (Schema::hasColumn('b2c_cotizaciones', 'requiere_seguro_envio')) {
                $table->dropColumn('requiere_seguro_envio');
            }
        });
    }
};
