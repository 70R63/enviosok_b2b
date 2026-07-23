<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'b2c_invoice_requests',
            function (Blueprint $table) {
                $table->string('cfdi_rfc_emisor', 13)
                    ->nullable();

                $table->string('cfdi_rfc_receptor', 13)
                    ->nullable();

                $table->string('cfdi_nombre_receptor', 255)
                    ->nullable();

                $table->decimal('cfdi_total', 12, 2)
                    ->nullable();

                $table->timestamp('cfdi_fecha_emision')
                    ->nullable();

                $table->timestamp('cfdi_fecha_timbrado')
                    ->nullable();

                $table->unique('cfdi_uuid');
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'b2c_invoice_requests',
            function (Blueprint $table) {
                $table->dropUnique(['cfdi_uuid']);

                $table->dropColumn([
                    'cfdi_rfc_emisor',
                    'cfdi_rfc_receptor',
                    'cfdi_nombre_receptor',
                    'cfdi_total',
                    'cfdi_fecha_emision',
                    'cfdi_fecha_timbrado',
                ]);
            }
        );
    }
};
