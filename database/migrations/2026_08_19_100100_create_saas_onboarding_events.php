<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_onboarding_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('onboarding_application_id')
                ->constrained('saas_onboarding_applications')->restrictOnDelete();
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24)->nullable();
            $table->string('event', 80);
            $table->string('actor_type', 32);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('correlation_key', 191)->nullable();
            $table->unsignedBigInteger('payment_event_id')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(
                ['onboarding_application_id', 'created_at'],
                'saas_onboarding_events_timeline_idx'
            );
            $table->index('correlation_key', 'saas_onboarding_events_correlation_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_onboarding_events');
    }
};
