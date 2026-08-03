<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { if (!Schema::hasTable('zigo_provider_api_events')) Schema::create('zigo_provider_api_events', function (Blueprint $table): void { $table->id(); $table->string('provider',50)->index(); $table->string('operation',80); $table->string('environment',30)->index(); $table->uuid('correlation_id')->index(); $table->string('status',50)->index(); $table->unsignedSmallInteger('http_status')->nullable(); $table->string('provider_code',100)->nullable(); $table->unsignedInteger('duration_ms')->default(0); $table->unsignedSmallInteger('retry_count')->default(0); $table->foreignId('requested_by_user_id')->nullable()->index(); $table->json('metadata')->nullable(); $table->timestamps(); }); }
    public function down(): void { Schema::dropIfExists('zigo_provider_api_events'); }
};
