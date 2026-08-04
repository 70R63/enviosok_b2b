<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('b2c_cotizaciones', function (Blueprint $table) {
            $table->string('guia_provider_shipment_id', 191)->nullable();
            $table->string('guia_provider_status', 80)->nullable();
            $table->string('guia_label_format', 20)->nullable();
            $table->string('guia_request_fingerprint', 64)->nullable();
            $table->unsignedBigInteger('guia_generated_by')->nullable();
            $table->unique('guia_request_fingerprint', 'b2c_guia_request_fingerprint_unique');
        });
    }

    public function down(): void
    {
        Schema::table('b2c_cotizaciones', function (Blueprint $table) {
            $table->dropUnique('b2c_guia_request_fingerprint_unique');
            $table->dropColumn([
                'guia_provider_shipment_id', 'guia_provider_status', 'guia_label_format',
                'guia_request_fingerprint', 'guia_generated_by',
            ]);
        });
    }
};
