<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'zigo_provider_rate_references',
            function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger(
                    'agreement_service_id'
                );
                $table->string('name', 160);
                $table->unsignedInteger('version')->default(1);
                $table->string('status', 20)->default('DRAFT');
                $table->string('currency', 3)->default('MXN');
                $table->decimal(
                    'tax_percentage',
                    5,
                    2
                )->default(16);
                $table->decimal(
                    'included_weight_kg',
                    10,
                    3
                )->nullable();
                $table->decimal('base_price', 12, 2);
                $table->decimal(
                    'additional_weight_unit_kg',
                    10,
                    3
                )->nullable();
                $table->decimal(
                    'additional_weight_price',
                    12,
                    2
                )->default(0);
                $table->date('valid_from')->nullable();
                $table->date('valid_to')->nullable();
                $table->string(
                    'source_reference',
                    160
                )->nullable();
                $table->string(
                    'document_path',
                    255
                )->nullable();
                $table->string(
                    'document_original_name',
                    255
                )->nullable();
                $table->string(
                    'document_mime_type',
                    120
                )->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger(
                    'created_by'
                )->nullable();
                $table->unsignedBigInteger(
                    'updated_by'
                )->nullable();
                $table->timestamps();

                $table->foreign(
                    'agreement_service_id',
                    'zprr_agreement_service_fk'
                )
                    ->references('id')
                    ->on('zigo_agreement_services')
                    ->cascadeOnDelete();

                $table->foreign(
                    'created_by',
                    'zprr_created_by_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->foreign(
                    'updated_by',
                    'zprr_updated_by_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->unique(
                    [
                        'agreement_service_id',
                        'version',
                    ],
                    'zprr_service_version_uq'
                );

                $table->index(
                    [
                        'agreement_service_id',
                        'status',
                    ],
                    'zprr_active_lookup_idx'
                );

                $table->index(
                    [
                        'valid_from',
                        'valid_to',
                    ],
                    'zprr_validity_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'zigo_provider_rate_references'
        );
    }
};
