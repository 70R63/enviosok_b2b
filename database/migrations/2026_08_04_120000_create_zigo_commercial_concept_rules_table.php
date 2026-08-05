<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('zigo_commercial_concept_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('carrier', 50)->default('ESTAFETA');
            $table->string('service', 50)->default('all');
            $table->string('segment', 30)->default('all');
            $table->string('plan', 50)->nullable();
            $table->string('package_type', 20)->default('all');
            $table->string('concept', 30);
            $table->string('adjustment_type', 20);
            $table->decimal('value', 12, 4)->default(0);
            $table->unsignedInteger('priority')->default(100);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['carrier', 'concept', 'active', 'priority'], 'zccr_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zigo_commercial_concept_rules');
    }
};
