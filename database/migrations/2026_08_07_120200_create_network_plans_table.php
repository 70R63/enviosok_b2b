<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('network_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->decimal('monthly_price', 12, 2)->nullable();
            $table->decimal('annual_price', 12, 2)->nullable();
            $table->char('currency', 3)->default('MXN');
            $table->unsignedInteger('included_operations')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('network_plans'); }
};
