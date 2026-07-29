<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zigo_provider_rate_cards', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('agreement_service_id');
            $table->string('name', 160);
            $table->string('pricing_scheme', 30);
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 20)->default('DRAFT');
            $table->string('currency', 3)->default('MXN');
            $table->decimal('tax_percentage', 5, 2)->default(16);
            $table->unsignedInteger('priority')->default(100);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->string('source_reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('agreement_service_id', 'zprc_agreement_service_fk')
                ->references('id')
                ->on('zigo_agreement_services')
                ->cascadeOnDelete();

            $table->foreign('created_by', 'zprc_created_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('updated_by', 'zprc_updated_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->unique(
                ['agreement_service_id', 'version'],
                'zprc_service_version_uq'
            );

            $table->index(
                ['agreement_service_id', 'status', 'priority'],
                'zprc_lookup_idx'
            );

            $table->index(
                ['valid_from', 'valid_to'],
                'zprc_validity_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zigo_provider_rate_cards');
    }
};
