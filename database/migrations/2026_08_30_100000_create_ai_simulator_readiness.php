<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_runtime_runs', function (Blueprint $table): void {
            $table->string('execution_mode', 20)->default('live')->after('purpose');
            $table->index(['tenant_id', 'execution_mode', 'created_at'], 'ai_runtime_runs_mode_ix');
        });

        Schema::table('ai_agent_versions', function (Blueprint $table): void {
            $table->unique(['tenant_id','agent_id','id','agent_contract_version_id'], 'ai_agent_versions_sim_contract_uq');
        });

        Schema::create('ai_simulation_scenarios', function (Blueprint $table): void {
            $table->id(); $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('network_tenants')->restrictOnDelete();
            $table->unsignedBigInteger('agent_id'); $table->string('name', 160); $table->text('description')->nullable();
            $table->boolean('enabled')->default(true); $table->text('definition'); $table->timestamps();
            $table->unique(['tenant_id','id'], 'ai_sim_scenarios_tenant_id_uq');
            $table->unique(['tenant_id','agent_id','id'], 'ai_sim_scenarios_owner_id_uq');
            $table->index(['tenant_id','agent_id','enabled'], 'ai_sim_scenarios_enabled_ix');
            $table->foreign(['tenant_id','agent_id'], 'ai_sim_scenarios_agent_fk')->references(['tenant_id','id'])->on('ai_agents')->restrictOnDelete();
        });

        Schema::create('ai_simulation_runs', function (Blueprint $table): void {
            $table->id(); $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('network_tenants')->restrictOnDelete();
            $table->unsignedBigInteger('agent_id'); $table->unsignedBigInteger('agent_version_id'); $table->unsignedBigInteger('contract_version_id');
            $table->string('status', 20); $table->char('scenario_set_fingerprint', 64); $table->json('summary')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('started_at'); $table->timestamp('completed_at')->nullable(); $table->timestamps();
            $table->unique(['tenant_id','id'], 'ai_sim_runs_tenant_id_uq');
            $table->unique(['tenant_id','agent_id','id'], 'ai_sim_runs_owner_id_uq');
            $table->index(['tenant_id','agent_id','created_at'], 'ai_sim_runs_agent_ix');
            $table->foreign(['tenant_id','agent_id'], 'ai_sim_runs_agent_fk')->references(['tenant_id','id'])->on('ai_agents')->restrictOnDelete();
            $table->foreign(['tenant_id','agent_id','agent_version_id','contract_version_id'], 'ai_sim_runs_version_contract_fk')->references(['tenant_id','agent_id','id','agent_contract_version_id'])->on('ai_agent_versions')->restrictOnDelete();
            $table->foreign(['tenant_id','contract_version_id'], 'ai_sim_runs_contract_fk')->references(['tenant_id','id'])->on('ai_agent_contract_versions')->restrictOnDelete();
        });

        Schema::create('ai_simulation_case_runs', function (Blueprint $table): void {
            $table->id(); $table->uuid('uuid')->unique(); $table->foreignId('tenant_id')->constrained('network_tenants')->restrictOnDelete();
            $table->unsignedBigInteger('simulation_run_id'); $table->unsignedBigInteger('scenario_id'); $table->string('status', 20);
            foreach (['scenario_snapshot','observations','transcript','action_traces'] as $column) $table->text($column);
            $table->string('safe_failure_code', 40)->nullable(); $table->timestamp('started_at'); $table->timestamp('completed_at')->nullable(); $table->timestamps();
            $table->unique(['simulation_run_id','scenario_id'], 'ai_sim_case_once_uq');
            $table->foreign(['tenant_id','simulation_run_id'], 'ai_sim_cases_run_fk')->references(['tenant_id','id'])->on('ai_simulation_runs')->restrictOnDelete();
            $table->foreign(['tenant_id','scenario_id'], 'ai_sim_cases_scenario_fk')->references(['tenant_id','id'])->on('ai_simulation_scenarios')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_simulation_case_runs'); Schema::dropIfExists('ai_simulation_runs'); Schema::dropIfExists('ai_simulation_scenarios');
        Schema::table('ai_agent_versions', function (Blueprint $table): void { $table->dropUnique('ai_agent_versions_sim_contract_uq'); });
        Schema::table('ai_runtime_runs', function (Blueprint $table): void { $table->dropIndex('ai_runtime_runs_mode_ix'); $table->dropColumn('execution_mode'); });
    }
};
