<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_client_id')
                ->constrained('api_clients')
                ->cascadeOnDelete();
            $table->string('name');
            $table->string('environment', 20);
            $table->string('url', 2048);
            $table->longText('secret_encrypted');
            $table->string('secret_prefix', 32);
            $table->json('events');
            $table->boolean('active')->default(true);
            $table->timestamp('last_delivery_at')->nullable();
            $table->timestamps();

            $table->index([
                'api_client_id',
                'environment',
                'active',
            ], 'api_webhook_endpoint_lookup');
        });

        Schema::create('api_webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_webhook_endpoint_id')
                ->constrained('api_webhook_endpoints')
                ->cascadeOnDelete();
            $table->foreignId('api_client_id')
                ->constrained('api_clients')
                ->cascadeOnDelete();
            $table->foreignId('api_billing_request_id')
                ->nullable()
                ->constrained('api_billing_requests')
                ->cascadeOnDelete();
            $table->uuid('event_id')->unique();
            $table->string('event', 100);
            $table->string('status', 20)->default('PENDING');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('max_attempts')->default(5);
            $table->unsignedBigInteger('signature_timestamp');
            $table->string('signature', 80);
            $table->longText('payload_json');
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->longText('response_body')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique([
                'api_webhook_endpoint_id',
                'api_billing_request_id',
                'event',
            ], 'api_webhook_delivery_event_unique');

            $table->index([
                'status',
                'next_attempt_at',
            ], 'api_webhook_delivery_queue');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_webhook_deliveries');
        Schema::dropIfExists('api_webhook_endpoints');
    }
};
