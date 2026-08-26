<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('ai_provider_rates', function(Blueprint $t){$t->id();$t->string('provider',40);$t->string('model',120);$t->decimal('input_cost_per_million',16,8);$t->decimal('cached_input_cost_per_million',16,8)->default(0);$t->decimal('output_cost_per_million',16,8);$t->timestamp('effective_from');$t->boolean('enabled')->default(true);$t->timestamps();$t->unique(['provider','model','effective_from'],'ai_rates_provider_model_effective_uq');});
  Schema::create('ai_cost_ledger', function(Blueprint $t){$t->id();$t->uuid('uuid')->unique('ai_cost_ledger_uuid_uq');$t->foreignId('tenant_id')->constrained('network_tenants')->cascadeOnDelete();$t->foreignId('agent_id')->nullable()->constrained('ai_agents')->nullOnDelete();$t->foreignId('conversation_id')->nullable()->constrained('ai_conversations')->nullOnDelete();$t->foreignId('runtime_run_id')->nullable()->constrained('ai_runtime_runs')->nullOnDelete();$t->string('provider',40);$t->string('model',120);$t->unsignedInteger('input_tokens')->default(0);$t->unsignedInteger('cached_input_tokens')->default(0);$t->unsignedInteger('output_tokens')->default(0);$t->unsignedInteger('total_tokens')->default(0);$t->decimal('cost_usd',16,8)->default(0);$t->string('billing_period',20);$t->string('idempotency_key',160);$t->timestamps();$t->unique(['tenant_id','idempotency_key'],'ai_cost_ledger_idempotency_uq');$t->index(['tenant_id','billing_period'],'ai_cost_ledger_period_idx');});
  Schema::create('ai_cost_budgets', function(Blueprint $t){$t->id();$t->foreignId('tenant_id')->nullable()->constrained('network_tenants')->cascadeOnDelete();$t->decimal('budget_usd',16,8);$t->string('period',20);$t->timestamps();$t->unique(['tenant_id','period'],'ai_cost_budget_tenant_period_uq');});
 } public function down(): void {Schema::dropIfExists('ai_cost_budgets');Schema::dropIfExists('ai_cost_ledger');Schema::dropIfExists('ai_provider_rates');}
};
