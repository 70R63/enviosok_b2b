<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zigo_agreement_services', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('shipping_agreement_id');
            $table->unsignedBigInteger('servicio_id');
            $table->string('external_service_code', 80)->nullable();
            $table->string('display_name', 120)->nullable();
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('shipping_agreement_id', 'zas_agreement_fk')
                ->references('id')
                ->on('zigo_shipping_agreements')
                ->cascadeOnDelete();

            $table->foreign('servicio_id', 'zas_service_fk')
                ->references('id')
                ->on('servicios')
                ->restrictOnDelete();

            $table->unique(
                ['shipping_agreement_id', 'servicio_id'],
                'zas_agreement_service_uq'
            );

            $table->index(
                ['shipping_agreement_id', 'active', 'priority'],
                'zas_lookup_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zigo_agreement_services');
    }
};
