<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('network_plan_modules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained('network_plans')->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('network_modules')->cascadeOnDelete();
            $table->boolean('is_included')->default(true);
            $table->unsignedInteger('limit_value')->nullable();
            $table->timestamps();
            $table->unique(['plan_id', 'module_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('network_plan_modules'); }
};
