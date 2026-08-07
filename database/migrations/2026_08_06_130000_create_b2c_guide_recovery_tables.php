<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('b2c_guide_recovery_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cotizacion_id')->constrained('b2c_cotizaciones')->cascadeOnDelete();
            $table->string('email_hash', 64);
            $table->string('token_hash', 64)->unique();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('max_attempts')->default(10);
            $table->timestamp('expires_at');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['cotizacion_id', 'expires_at']);
        });

        Schema::create('b2c_guide_recovery_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cotizacion_id')->unique()->constrained('b2c_cotizaciones')->cascadeOnDelete();
            $table->string('classification', 40);
            $table->string('status', 30)->default('OPEN');
            $table->decimal('original_paid_amount', 10, 2)->nullable();
            $table->decimal('requote_total', 10, 2)->nullable();
            $table->timestamp('requote_reviewed_at')->nullable();
            $table->unsignedBigInteger('requote_reviewed_by')->nullable();
            $table->timestamps();
        });

        Schema::create('b2c_guide_recovery_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cotizacion_id')->constrained('b2c_cotizaciones')->cascadeOnDelete();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('action', 50);
            $table->string('result', 30);
            $table->string('correlation_id', 100)->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['cotizacion_id', 'action']);
        });

        Schema::table('b2c_cotizaciones', function (Blueprint $table) {
            $table->timestamp('guide_processing_email_sent_at')->nullable();
            $table->timestamp('guide_generated_email_sent_at')->nullable();
            $table->timestamp('guide_pending_email_sent_at')->nullable();
            $table->timestamp('guide_pdf_recovered_email_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('b2c_cotizaciones', fn (Blueprint $table) => $table->dropColumn([
            'guide_processing_email_sent_at', 'guide_generated_email_sent_at',
            'guide_pending_email_sent_at', 'guide_pdf_recovered_email_sent_at',
        ]));
        Schema::dropIfExists('b2c_guide_recovery_audits');
        Schema::dropIfExists('b2c_guide_recovery_cases');
        Schema::dropIfExists('b2c_guide_recovery_tokens');
    }
};
