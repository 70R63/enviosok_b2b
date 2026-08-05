<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE b2c_cotizaciones MODIFY guia_id VARCHAR(50) NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE b2c_cotizaciones ALTER COLUMN guia_id TYPE VARCHAR(50) USING guia_id::VARCHAR');
        }
    }

    public function down(): void
    {
        // No se revierte a BIGINT: un wayBill Xperta válido puede exceder su rango.
    }
};
