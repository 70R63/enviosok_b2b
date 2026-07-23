<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'b2c_invoice_requests',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('user_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->foreignId('cotizacion_id')
                    ->unique()
                    ->constrained(
                        'b2c_cotizaciones'
                    )
                    ->cascadeOnDelete();

                $table->foreignId(
                    'fiscal_profile_id'
                )
                    ->nullable()
                    ->constrained(
                        'b2c_fiscal_profiles'
                    )
                    ->nullOnDelete();

                $table->string(
                    'payment_reference',
                    255
                )->nullable();

                $table->string(
                    'payment_status',
                    50
                )->nullable();

                $table->string(
                    'payment_method',
                    50
                )->nullable();

                $table->string(
                    'metodo_pago',
                    3
                )->default('PUE');

                $table->string(
                    'forma_pago',
                    3
                )->nullable();

                $table->decimal(
                    'monto',
                    12,
                    2
                )->default(0);

                $table->string(
                    'razon_social',
                    255
                );

                $table->string(
                    'rfc',
                    13
                );

                $table->string(
                    'codigo_postal_fiscal',
                    5
                );

                $table->string(
                    'direccion_fiscal',
                    500
                )->nullable();

                $table->string(
                    'regimen_fiscal',
                    3
                );

                $table->string(
                    'uso_cfdi',
                    4
                );

                $table->string(
                    'email_facturacion',
                    255
                );

                $table->string(
                    'status',
                    30
                )
                    ->default('SOLICITADA')
                    ->index();

                $table->timestamp(
                    'solicitada_at'
                )->nullable();

                $table->timestamp(
                    'facturada_at'
                )->nullable();

                $table->uuid(
                    'cfdi_uuid'
                )->nullable();

                $table->string(
                    'pdf_path',
                    500
                )->nullable();

                $table->string(
                    'xml_path',
                    500
                )->nullable();

                $table->text(
                    'error_message'
                )->nullable();

                $table->timestamps();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'b2c_invoice_requests'
        );
    }
};