<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up():void
    {
        Schema::create('ai_agents',function(Blueprint$t):void{
            $t->id();$t->foreignId('tenant_id')->constrained('network_tenants')->restrictOnDelete();
            $t->string('code',80);$t->string('name',160);$t->text('description')->nullable();$t->string('type',40);$t->string('status',20)->default('draft');
            $t->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();$t->timestamps();
            $t->unique(['tenant_id','code'],'ai_agents_tenant_code_uq');$t->unique(['tenant_id','id'],'ai_agents_tenant_id_uq');$t->index(['tenant_id','status'],'ai_agents_tenant_status_ix');
        });
        Schema::create('ai_agent_contracts',function(Blueprint$t):void{
            $t->id();$t->foreignId('tenant_id')->constrained('network_tenants')->restrictOnDelete();$t->unsignedBigInteger('agent_id');$t->string('status',20)->default('draft');
            $t->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();$t->timestamps();
            $t->unique(['tenant_id','agent_id'],'ai_contracts_tenant_agent_uq');$t->unique(['tenant_id','id'],'ai_contracts_tenant_id_uq');$t->index(['tenant_id','status'],'ai_contracts_tenant_status_ix');
            $t->foreign(['tenant_id','agent_id'],'ai_contracts_tenant_agent_fk')->references(['tenant_id','id'])->on('ai_agents')->restrictOnDelete();
        });
        Schema::create('ai_agent_contract_versions',function(Blueprint$t):void{
            $t->id();$t->foreignId('tenant_id')->constrained('network_tenants')->restrictOnDelete();$t->unsignedBigInteger('agent_contract_id');$t->unsignedInteger('version_number');$t->string('status',20)->default('draft');$t->text('job_to_be_done');
            foreach(['objectives','allowed_capabilities','prohibited_capabilities','channels','knowledge_requirements','allowed_actions','handoff_policy','outcome_policy','capacity_policy','sla_policy','privacy_policy','pricing_policy']as$column)$t->json($column);
            $t->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();$t->timestamps();
            $t->unique(['agent_contract_id','version_number'],'ai_contract_versions_number_uq');$t->unique(['tenant_id','id'],'ai_contract_versions_tenant_id_uq');$t->index(['tenant_id','status'],'ai_contract_versions_tenant_status_ix');
            $t->foreign(['tenant_id','agent_contract_id'],'ai_contract_versions_tenant_contract_fk')->references(['tenant_id','id'])->on('ai_agent_contracts')->restrictOnDelete();
        });
        Schema::create('ai_agent_versions',function(Blueprint$t):void{
            $t->id();$t->foreignId('tenant_id')->constrained('network_tenants')->restrictOnDelete();$t->unsignedBigInteger('agent_id');$t->unsignedBigInteger('agent_contract_version_id')->nullable();$t->unsignedInteger('version_number');$t->string('status',20)->default('draft');$t->string('schema_version',30);$t->json('configuration');
            $t->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();$t->timestamps();
            $t->unique(['agent_id','version_number'],'ai_agent_versions_number_uq');$t->index(['tenant_id','status'],'ai_agent_versions_tenant_status_ix');
            $t->foreign(['tenant_id','agent_id'],'ai_agent_versions_tenant_agent_fk')->references(['tenant_id','id'])->on('ai_agents')->restrictOnDelete();
            $t->foreign(['tenant_id','agent_contract_version_id'],'ai_agent_versions_tenant_contract_version_fk')->references(['tenant_id','id'])->on('ai_agent_contract_versions')->restrictOnDelete();
        });
    }
    public function down():void
    {
        Schema::dropIfExists('ai_agent_versions');Schema::dropIfExists('ai_agent_contract_versions');Schema::dropIfExists('ai_agent_contracts');Schema::dropIfExists('ai_agents');
    }
};
