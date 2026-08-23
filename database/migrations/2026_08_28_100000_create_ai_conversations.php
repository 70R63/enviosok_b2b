<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys=ON');
        }
        Schema::table('ai_knowledge_chunks', fn (Blueprint $t) => $t->unique(['tenant_id', 'id'], 'ai_chunks_tenant_id_uq'));
        Schema::create('ai_conversations', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid');
            $t->foreignId('tenant_id');
            $t->unsignedBigInteger('agent_id');
            $t->unsignedBigInteger('agent_version_id');
            $t->string('channel', 24);
            $t->string('status', 24);
            $t->boolean('turn_in_progress')->default(false);
            $t->unsignedInteger('next_sequence')->default(1);
            $t->foreignId('created_by_user_id');
            $t->timestamp('closed_at')->nullable();
            $t->unsignedBigInteger('closed_by_user_id')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'uuid'], 'ai_conv_tenant_uuid_uq');
            $t->unique(['tenant_id', 'id'], 'ai_conv_tenant_id_uq');
            $t->unique(['tenant_id', 'id', 'agent_id', 'agent_version_id'], 'ai_conv_identity_uq');
            $t->index(['tenant_id', 'status'], 'ai_conv_tenant_status_ix');
            $t->index(['tenant_id', 'agent_id', 'agent_version_id'], 'ai_conv_agent_version_ix');
            $t->foreign('tenant_id', 'ai_conv_tenant_fk')->references('id')->on('network_tenants')->restrictOnDelete();
            $t->foreign(['tenant_id', 'agent_id'], 'ai_conv_agent_fk')->references(['tenant_id', 'id'])->on('ai_agents')->restrictOnDelete();
            $t->foreign(['tenant_id', 'agent_id', 'agent_version_id'], 'ai_conv_version_fk')->references(['tenant_id', 'agent_id', 'id'])->on('ai_agent_versions')->restrictOnDelete();
            $t->foreign('created_by_user_id', 'ai_conv_creator_fk')->references('id')->on('users')->restrictOnDelete();
            $t->foreign('closed_by_user_id', 'ai_conv_closer_fk')->references('id')->on('users')->restrictOnDelete();
        });
        Schema::create('ai_conversation_messages', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid');
            $t->foreignId('tenant_id');
            $t->unsignedBigInteger('conversation_id');
            $t->unsignedInteger('sequence');
            $t->string('role', 16);
            $t->string('status', 16);
            $t->text('content')->nullable();
            $t->unsignedBigInteger('runtime_run_id')->nullable();
            $t->string('confidence', 10)->nullable();
            $t->boolean('needs_handoff')->default(false);
            $t->string('handoff_reason', 40)->nullable();
            $t->string('safe_error_code', 40)->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamp('failed_at')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'uuid'], 'ai_msg_tenant_uuid_uq');
            $t->unique(['tenant_id', 'id'], 'ai_msg_tenant_id_uq');
            $t->unique(['conversation_id', 'sequence'], 'ai_msg_conversation_sequence_uq');
            $t->index(['tenant_id', 'conversation_id', 'status'], 'ai_msg_conv_status_ix');
            $t->foreign(['tenant_id', 'conversation_id'], 'ai_msg_conversation_fk')->references(['tenant_id', 'id'])->on('ai_conversations')->restrictOnDelete();
            $t->foreign(['tenant_id', 'runtime_run_id'], 'ai_msg_runtime_fk')->references(['tenant_id', 'id'])->on('ai_runtime_runs')->restrictOnDelete();
        });
        Schema::create('ai_conversation_message_citations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id');
            $t->unsignedBigInteger('conversation_message_id');
            $t->unsignedBigInteger('knowledge_chunk_id');
            $t->string('label', 3);
            $t->unsignedTinyInteger('rank');
            $t->timestamps();
            $t->unique(['conversation_message_id', 'knowledge_chunk_id'], 'ai_cite_message_chunk_uq');
            $t->unique(['conversation_message_id', 'label'], 'ai_cite_message_label_uq');
            $t->index(['tenant_id', 'conversation_message_id'], 'ai_cite_message_ix');
            $t->foreign(['tenant_id', 'conversation_message_id'], 'ai_cite_message_fk')->references(['tenant_id', 'id'])->on('ai_conversation_messages')->restrictOnDelete();
            $t->foreign(['tenant_id', 'knowledge_chunk_id'], 'ai_cite_chunk_fk')->references(['tenant_id', 'id'])->on('ai_knowledge_chunks')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys=ON');
        }Schema::dropIfExists('ai_conversation_message_citations');
        Schema::dropIfExists('ai_conversation_messages');
        Schema::dropIfExists('ai_conversations');
        Schema::table('ai_knowledge_chunks',fn (Blueprint $t) => $t->dropUnique('ai_chunks_tenant_id_uq'));
    }
};
