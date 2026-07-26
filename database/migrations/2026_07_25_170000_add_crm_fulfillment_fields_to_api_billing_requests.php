<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'api_billing_requests',
            function (Blueprint $table) {
                $table->foreignId('managed_by_user_id')
                    ->nullable()
                    ->after('api_key_id')
                    ->constrained('users')
                    ->nullOnDelete();

                $table->text('internal_notes')
                    ->nullable()
                    ->after('response_payload');
                $table->text('rejection_reason')
                    ->nullable()
                    ->after('internal_notes');
                $table->text('cancellation_reason')
                    ->nullable()
                    ->after('rejection_reason');

                $table->string('cfdi_rfc_emisor', 13)
                    ->nullable()
                    ->after('cfdi_uuid');
                $table->string('cfdi_rfc_receptor', 13)
                    ->nullable()
                    ->after('cfdi_rfc_emisor');
                $table->string('cfdi_nombre_receptor', 254)
                    ->nullable()
                    ->after('cfdi_rfc_receptor');
                $table->decimal('cfdi_total', 14, 2)
                    ->nullable()
                    ->after('cfdi_nombre_receptor');
                $table->timestamp('cfdi_fecha_emision')
                    ->nullable()
                    ->after('cfdi_total');
                $table->timestamp('cfdi_fecha_timbrado')
                    ->nullable()
                    ->after('cfdi_fecha_emision');
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'api_billing_requests',
            function (Blueprint $table) {
                $table->dropForeign([
                    'managed_by_user_id',
                ]);

                $table->dropColumn([
                    'managed_by_user_id',
                    'internal_notes',
                    'rejection_reason',
                    'cancellation_reason',
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
