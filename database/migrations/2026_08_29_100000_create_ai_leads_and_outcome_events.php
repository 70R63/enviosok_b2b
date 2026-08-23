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

        Schema::create('ai_leads', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid');
            $t->foreignId('tenant_id');
            $t->unsignedBigInteger('agent_id');
            $t->unsignedBigInteger('agent_version_id');
            $t->unsignedBigInteger('agent_contract_version_id');
            $t->unsignedBigInteger('conversation_id');
            $t->unsignedBigInteger('source_message_id');
            $t->unsignedBigInteger('runtime_run_id');
            $t->string('status', 16);
            $t->text('data');
            $t->timestamp('detected_at');
            $t->timestamp('verified_at')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'uuid'], 'ai_leads_tenant_uuid_uq');
            $t->unique(['tenant_id', 'id'], 'ai_leads_tenant_id_uq');
            $t->unique(['tenant_id', 'conversation_id'], 'ai_leads_conversation_uq');
            $t->unique(['tenant_id', 'conversation_id', 'id'], 'ai_leads_outcome_identity_uq');
            $t->index(['tenant_id', 'status', 'detected_at'], 'ai_leads_status_ix');
            $t->foreign('tenant_id', 'ai_leads_tenant_fk')->references('id')->on('network_tenants')->restrictOnDelete();
            $t->foreign(['tenant_id', 'agent_id'], 'ai_leads_agent_fk')->references(['tenant_id', 'id'])->on('ai_agents')->restrictOnDelete();
            $t->foreign(['tenant_id', 'agent_id', 'agent_version_id'], 'ai_leads_version_fk')->references(['tenant_id', 'agent_id', 'id'])->on('ai_agent_versions')->restrictOnDelete();
            $t->foreign(['tenant_id', 'agent_contract_version_id'], 'ai_leads_contract_version_fk')->references(['tenant_id', 'id'])->on('ai_agent_contract_versions')->restrictOnDelete();
            $t->foreign(['tenant_id', 'conversation_id'], 'ai_leads_conversation_fk')->references(['tenant_id', 'id'])->on('ai_conversations')->restrictOnDelete();
            $t->foreign(['tenant_id', 'source_message_id'], 'ai_leads_message_fk')->references(['tenant_id', 'id'])->on('ai_conversation_messages')->restrictOnDelete();
            $t->foreign(['tenant_id', 'runtime_run_id'], 'ai_leads_run_fk')->references(['tenant_id', 'id'])->on('ai_runtime_runs')->restrictOnDelete();
        });

        Schema::create('ai_outcome_events', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid');
            $t->foreignId('tenant_id');
            $t->unsignedBigInteger('agent_id');
            $t->unsignedBigInteger('agent_version_id');
            $t->unsignedBigInteger('agent_contract_version_id');
            $t->unsignedBigInteger('conversation_id');
            $t->unsignedBigInteger('source_message_id');
            $t->unsignedBigInteger('runtime_run_id');
            $t->unsignedBigInteger('lead_id')->nullable();
            $t->string('outcome_type', 32);
            $t->string('status', 16);
            $t->json('evidence');
            $t->timestamp('detected_at');
            $t->timestamp('verified_at')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'uuid'], 'ai_outcomes_tenant_uuid_uq');
            $t->unique(['tenant_id', 'conversation_id', 'outcome_type', 'runtime_run_id'], 'ai_outcomes_logical_uq');
            $t->index(['tenant_id', 'conversation_id', 'outcome_type'], 'ai_outcomes_conversation_ix');
            $t->foreign('tenant_id', 'ai_outcomes_tenant_fk')->references('id')->on('network_tenants')->restrictOnDelete();
            $t->foreign(['tenant_id', 'agent_id'], 'ai_outcomes_agent_fk')->references(['tenant_id', 'id'])->on('ai_agents')->restrictOnDelete();
            $t->foreign(['tenant_id', 'agent_id', 'agent_version_id'], 'ai_outcomes_version_fk')->references(['tenant_id', 'agent_id', 'id'])->on('ai_agent_versions')->restrictOnDelete();
            $t->foreign(['tenant_id', 'agent_contract_version_id'], 'ai_outcomes_contract_version_fk')->references(['tenant_id', 'id'])->on('ai_agent_contract_versions')->restrictOnDelete();
            $t->foreign(['tenant_id', 'conversation_id'], 'ai_outcomes_conversation_fk')->references(['tenant_id', 'id'])->on('ai_conversations')->restrictOnDelete();
            $t->foreign(['tenant_id', 'source_message_id'], 'ai_outcomes_message_fk')->references(['tenant_id', 'id'])->on('ai_conversation_messages')->restrictOnDelete();
            $t->foreign(['tenant_id', 'runtime_run_id'], 'ai_outcomes_run_fk')->references(['tenant_id', 'id'])->on('ai_runtime_runs')->restrictOnDelete();
            $t->foreign(['tenant_id', 'conversation_id', 'lead_id'], 'ai_outcomes_lead_fk')->references(['tenant_id', 'conversation_id', 'id'])->on('ai_leads')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys=ON');
        }
        Schema::dropIfExists('ai_outcome_events');
        Schema::dropIfExists('ai_leads');
    }
};
