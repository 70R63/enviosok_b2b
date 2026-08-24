<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') DB::statement('PRAGMA foreign_keys=ON');
        Schema::create('ai_webchat_channels', function (Blueprint $t): void {
            $t->id(); $t->uuid('uuid'); $t->foreignId('tenant_id'); $t->unsignedBigInteger('agent_id');
            $t->string('public_key', 64); $t->boolean('enabled')->default(false);
            $t->string('display_name', 120); $t->string('welcome_message', 500)->nullable();
            $t->char('primary_color', 7)->default('#2563EB'); $t->string('launcher_label', 80)->default('Chat');
            $t->json('allowed_origins'); $t->foreignId('created_by_user_id'); $t->timestamps();
            $t->unique('public_key', 'ai_webchat_public_key_uq');
            $t->unique(['tenant_id','uuid'], 'ai_webchat_tenant_uuid_uq');
            $t->unique(['tenant_id','id'], 'ai_webchat_tenant_id_uq');
            $t->unique(['tenant_id','agent_id'], 'ai_webchat_agent_uq');
            $t->foreign('tenant_id', 'ai_webchat_tenant_fk')->references('id')->on('network_tenants')->restrictOnDelete();
            $t->foreign(['tenant_id','agent_id'], 'ai_webchat_agent_fk')->references(['tenant_id','id'])->on('ai_agents')->restrictOnDelete();
            $t->foreign('created_by_user_id', 'ai_webchat_creator_fk')->references('id')->on('users')->restrictOnDelete();
        });
        Schema::create('ai_webchat_channel_keys', function (Blueprint $t): void {
            $t->id(); $t->foreignId('tenant_id'); $t->unsignedBigInteger('webchat_channel_id'); $t->string('public_key',64); $t->timestamps();
            $t->unique('public_key','ai_webchat_retired_key_uq');
            $t->foreign(['tenant_id','webchat_channel_id'],'ai_webchat_key_channel_fk')->references(['tenant_id','id'])->on('ai_webchat_channels')->restrictOnDelete();
        });
        Schema::create('ai_webchat_sessions', function (Blueprint $t): void {
            $t->id(); $t->uuid('uuid'); $t->foreignId('tenant_id'); $t->unsignedBigInteger('webchat_channel_id');
            $t->unsignedBigInteger('conversation_id'); $t->char('token_hash', 64);
            $t->uuid('last_client_message_id')->nullable(); $t->unsignedInteger('last_response_sequence')->nullable();
            $t->timestamp('expires_at'); $t->timestamp('last_seen_at'); $t->timestamp('closed_at')->nullable(); $t->timestamps();
            $t->unique('token_hash', 'ai_webchat_session_token_uq');
            $t->unique(['tenant_id','uuid'], 'ai_webchat_session_uuid_uq');
            $t->unique(['tenant_id','id'], 'ai_webchat_session_id_uq');
            $t->unique(['webchat_channel_id','conversation_id'], 'ai_webchat_session_conversation_uq');
            $t->foreign('tenant_id', 'ai_webchat_session_tenant_fk')->references('id')->on('network_tenants')->restrictOnDelete();
            $t->foreign(['tenant_id','webchat_channel_id'], 'ai_webchat_session_channel_fk')->references(['tenant_id','id'])->on('ai_webchat_channels')->restrictOnDelete();
            $t->foreign(['tenant_id','conversation_id'], 'ai_webchat_session_conversation_fk')->references(['tenant_id','id'])->on('ai_conversations')->restrictOnDelete();
        });
        Schema::create('ai_webchat_message_receipts', function (Blueprint $t): void {
            $t->id(); $t->foreignId('tenant_id'); $t->unsignedBigInteger('webchat_session_id');
            $t->uuid('client_message_id'); $t->string('status', 16); $t->unsignedInteger('response_sequence')->nullable(); $t->timestamps();
            $t->unique(['webchat_session_id','client_message_id'], 'ai_webchat_message_idempotency_uq');
            $t->foreign(['tenant_id','webchat_session_id'], 'ai_webchat_receipt_session_fk')->references(['tenant_id','id'])->on('ai_webchat_sessions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') DB::statement('PRAGMA foreign_keys=ON');
        Schema::dropIfExists('ai_webchat_message_receipts');
        Schema::dropIfExists('ai_webchat_sessions');
        Schema::dropIfExists('ai_webchat_channel_keys');
        Schema::dropIfExists('ai_webchat_channels');
    }
};
