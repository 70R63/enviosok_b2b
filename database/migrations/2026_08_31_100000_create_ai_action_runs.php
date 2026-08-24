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
        Schema::table('ai_conversation_messages',fn(Blueprint$t)=>$t->unique(['tenant_id','conversation_id','id'],'ai_msg_action_identity_uq'));
        Schema::table('ai_runtime_runs',fn(Blueprint$t)=>$t->unique(['tenant_id','agent_id','agent_version_id','id'],'ai_runtime_action_identity_uq'));
        Schema::create('ai_action_runs', function (Blueprint $t): void {
            $t->id(); $t->uuid('uuid'); $t->foreignId('tenant_id');
            $t->unsignedBigInteger('agent_id'); $t->unsignedBigInteger('agent_version_id'); $t->unsignedBigInteger('contract_version_id');
            $t->unsignedBigInteger('conversation_id'); $t->unsignedBigInteger('source_message_id'); $t->unsignedBigInteger('runtime_run_id');
            $t->string('action_key', 64); $t->string('effect', 8); $t->string('status', 32); $t->char('idempotency_key', 64);
            $t->text('input'); $t->text('output')->nullable(); $t->string('safe_error_code', 48)->nullable();
            $t->timestamp('requested_at'); $t->timestamp('confirmed_at')->nullable(); $t->unsignedBigInteger('confirmed_by_user_id')->nullable();
            $t->timestamp('started_at')->nullable(); $t->timestamp('completed_at')->nullable(); $t->timestamp('failed_at')->nullable(); $t->timestamps();
            $t->unique(['tenant_id','uuid'],'ai_actions_tenant_uuid_uq');
            $t->unique(['tenant_id','id'],'ai_actions_tenant_id_uq');
            $t->unique(['tenant_id','idempotency_key'],'ai_actions_idempotency_uq');
            $t->index(['tenant_id','conversation_id','status'],'ai_actions_conversation_ix');
            $t->foreign('tenant_id','ai_actions_tenant_fk')->references('id')->on('network_tenants')->restrictOnDelete();
            $t->foreign(['tenant_id','agent_id'],'ai_actions_agent_fk')->references(['tenant_id','id'])->on('ai_agents')->restrictOnDelete();
            $t->foreign(['tenant_id','agent_id','agent_version_id'],'ai_actions_version_fk')->references(['tenant_id','agent_id','id'])->on('ai_agent_versions')->restrictOnDelete();
            $t->foreign(['tenant_id','contract_version_id'],'ai_actions_contract_fk')->references(['tenant_id','id'])->on('ai_agent_contract_versions')->restrictOnDelete();
            $t->foreign(['tenant_id','conversation_id','agent_id','agent_version_id'],'ai_actions_conversation_fk')->references(['tenant_id','id','agent_id','agent_version_id'])->on('ai_conversations')->restrictOnDelete();
            $t->foreign(['tenant_id','conversation_id','source_message_id'],'ai_actions_message_fk')->references(['tenant_id','conversation_id','id'])->on('ai_conversation_messages')->restrictOnDelete();
            $t->foreign(['tenant_id','agent_id','agent_version_id','runtime_run_id'],'ai_actions_runtime_fk')->references(['tenant_id','agent_id','agent_version_id','id'])->on('ai_runtime_runs')->restrictOnDelete();
            $t->foreign('confirmed_by_user_id','ai_actions_confirmer_fk')->references('id')->on('users')->restrictOnDelete();
        });
    }
    public function down(): void { Schema::dropIfExists('ai_action_runs');Schema::table('ai_runtime_runs',fn(Blueprint$t)=>$t->dropUnique('ai_runtime_action_identity_uq'));Schema::table('ai_conversation_messages',fn(Blueprint$t)=>$t->dropUnique('ai_msg_action_identity_uq')); }
};
