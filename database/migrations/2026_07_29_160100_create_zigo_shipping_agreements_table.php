<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zigo_shipping_agreements', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('provider_source_id');
            $table->unsignedBigInteger('ltd_id');
            $table->string('name', 160);
            $table->string('rate_mode', 30);
            $table->string('currency', 3)->default('MXN');
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('active')->default(true);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->string('external_reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('provider_source_id', 'zsa_source_fk')
                ->references('id')
                ->on('zigo_provider_sources')
                ->cascadeOnDelete();

            $table->foreign('ltd_id', 'zsa_ltd_fk')
                ->references('id')
                ->on('ltds')
                ->restrictOnDelete();

            $table->foreign('created_by', 'zsa_created_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('updated_by', 'zsa_updated_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(
                ['provider_source_id', 'ltd_id', 'active'],
                'zsa_source_ltd_active_idx'
            );

            $table->index(
                ['valid_from', 'valid_to'],
                'zsa_validity_idx'
            );

            $table->index(
                ['priority', 'active'],
                'zsa_priority_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zigo_shipping_agreements');
    }
};
