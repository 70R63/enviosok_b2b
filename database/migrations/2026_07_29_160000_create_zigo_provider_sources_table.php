<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zigo_provider_sources', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 120);
            $table->string('source_type', 30);
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(
                ['source_type', 'active'],
                'zps_type_active_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zigo_provider_sources');
    }
};
