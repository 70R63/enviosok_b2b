<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_webhook_deliveries', function (Blueprint $table) {
            $table->uuid('lock_token')
                ->nullable()
                ->after('next_attempt_at');
            $table->timestamp('locked_at')
                ->nullable()
                ->after('lock_token');
            $table->unsignedInteger('response_time_ms')
                ->nullable()
                ->after('response_status');

            $table->index([
                'status',
                'next_attempt_at',
                'locked_at',
            ], 'api_webhook_delivery_runtime_queue');
        });

        Schema::create(
            'api_webhook_delivery_attempts',
            function (Blueprint $table) {
                $table->id();
                $table->foreignId('api_webhook_delivery_id')
                    ->constrained('api_webhook_deliveries')
                    ->cascadeOnDelete();
                $table->unsignedSmallInteger('attempt_number');
                $table->timestamp('started_at');
                $table->timestamp('finished_at')->nullable();
                $table->unsignedInteger('duration_ms')->nullable();
                $table->string('request_url', 2048);
                $table->json('request_headers_json')->nullable();
                $table->unsignedSmallInteger('response_status')->nullable();
                $table->json('response_headers_json')->nullable();
                $table->longText('response_body')->nullable();
                $table->text('error_message')->nullable();
                $table->boolean('successful')->default(false);
                $table->timestamps();

                $table->unique([
                    'api_webhook_delivery_id',
                    'attempt_number',
                ], 'api_webhook_attempt_number_unique');

                $table->index([
                    'successful',
                    'response_status',
                ], 'api_webhook_attempt_result');
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('api_webhook_delivery_attempts');

        Schema::table('api_webhook_deliveries', function (Blueprint $table) {
            $table->dropIndex('api_webhook_delivery_runtime_queue');
            $table->dropColumn([
                'lock_token',
                'locked_at',
                'response_time_ms',
            ]);
        });
    }
};
