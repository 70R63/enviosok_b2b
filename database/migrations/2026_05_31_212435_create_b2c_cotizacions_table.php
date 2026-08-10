<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Historical no-op. This migration originally created the misspelled
        // legacy table b2c_cotizacions and was superseded by
        // 2026_05_31_213836_create_b2c_cotizaciones_table. It is intentionally
        // retained to preserve migration ordering without creating obsolete schema.
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Historical no-op. Neither the legacy nor canonical table may be
        // removed because historical installations can contain data in either.
    }
};
