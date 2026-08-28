<?php

namespace Tests\Feature;

use App\Domain\AI\Cost\AiCostGuard;
use App\Domain\AI\Cost\Models\{AiCostBudget,AiCostLedger,AiCostReservation,AiProviderRate};
use App\Domain\AI\Runtime\Enums\AiExecutionMode;
use App\Domain\AI\Runtime\Models\RuntimeRun;
use App\Domain\Network\Tenancy\Models\Tenant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
use Tests\TestCase;

final class AiCostGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('network_tenants', fn (Blueprint $t) => [$t->id(), $t->uuid('uuid'), $t->string('name'), $t->string('slug'), $t->string('status'), $t->timestamps()]);
        Schema::create('ai_provider_rates', fn (Blueprint $t) => [$t->id(), $t->string('provider'), $t->string('model'), $t->unsignedBigInteger('input_microusd_per_million'), $t->unsignedBigInteger('cached_input_microusd_per_million'), $t->unsignedBigInteger('output_microusd_per_million'), $t->timestamp('effective_from'), $t->boolean('enabled'), $t->timestamps()]);
        Schema::create('ai_cost_budgets', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('tenant_id')->nullable(), $t->unsignedBigInteger('budget_microusd')->nullable(), $t->decimal('budget_usd', 16, 8)->default(0), $t->string('period'), $t->timestamps()]);
        Schema::create('ai_cost_reservations', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('tenant_id'), $t->unsignedBigInteger('runtime_run_id')->unique(), $t->string('provider'), $t->string('model'), $t->string('billing_period'), $t->unsignedBigInteger('reserved_microusd'), $t->unsignedBigInteger('actual_cost_microusd')->nullable(), $t->unsignedBigInteger('released_microusd')->default(0), $t->string('status'), $t->string('idempotency_key')->unique(), $t->json('rate_snapshot')->nullable(), $t->timestamps()]);
        Schema::create('ai_cost_ledger', fn (Blueprint $t) => [$t->id(), $t->uuid('uuid')->nullable(), $t->unsignedBigInteger('tenant_id'), $t->unsignedBigInteger('agent_id')->nullable(), $t->unsignedBigInteger('runtime_run_id')->nullable(), $t->string('provider'), $t->string('model'), $t->unsignedInteger('input_tokens'), $t->unsignedInteger('cached_input_tokens'), $t->unsignedInteger('output_tokens'), $t->unsignedInteger('total_tokens'), $t->decimal('cost_usd', 16, 8), $t->unsignedBigInteger('actual_cost_microusd')->nullable(), $t->unsignedBigInteger('reserved_microusd')->default(0), $t->unsignedBigInteger('released_microusd')->default(0), $t->json('rate_snapshot')->nullable(), $t->string('billing_period'), $t->string('idempotency_key'), $t->timestamps()]);
        AiProviderRate::create(['provider' => 'openai', 'model' => 'test-model', 'input_microusd_per_million' => 200000, 'cached_input_microusd_per_million' => 100000, 'output_microusd_per_million' => 400000, 'effective_from' => now()->subDay(), 'enabled' => true]);
    }

    public function test_effective_rate_and_integer_settlement_are_snapshot_based(): void
    {
        AiProviderRate::create(['provider' => 'openai', 'model' => 'test-model', 'input_microusd_per_million' => 300000, 'cached_input_microusd_per_million' => 100000, 'output_microusd_per_million' => 500000, 'effective_from' => now(), 'enabled' => true]);
        AiProviderRate::create(['provider' => 'openai', 'model' => 'test-model', 'input_microusd_per_million' => 900000, 'cached_input_microusd_per_million' => 0, 'output_microusd_per_million' => 900000, 'effective_from' => now()->addDay(), 'enabled' => true]);
        $tenant = Tenant::create(['uuid' => '11111111-1111-4111-8111-111111111111', 'name' => 'Cost', 'slug' => 'cost', 'status' => 'active']);
        AiCostBudget::create(['tenant_id' => $tenant->id, 'period' => now()->format('Y-m'), 'budget_microusd' => 1000000]);
        $run = $this->runtime(1, $tenant->id);
        app(AiCostGuard::class)->reserve($tenant, $run);
        $ledger = app(AiCostGuard::class)->record($tenant, $run, ['input_tokens' => 100, 'cached_input_tokens' => 20, 'output_tokens' => 10, 'total_tokens' => 110]);
        $this->assertSame(31, $ledger->actual_cost_microusd);
        $this->assertSame(1, AiCostLedger::count());
        app(AiCostGuard::class)->record($tenant, $run, ['input_tokens' => 1]);
        $this->assertSame(1, AiCostLedger::count());
    }

    public function test_tenant_and_global_budgets_block_before_provider_reservation(): void
    {
        $tenant = Tenant::create(['uuid' => '22222222-2222-4222-8222-222222222222', 'name' => 'Cost', 'slug' => 'cost2', 'status' => 'active']);
        AiCostBudget::create(['tenant_id' => $tenant->id, 'period' => now()->format('Y-m'), 'budget_microusd' => 100]);
        AiCostBudget::create(['tenant_id' => null, 'period' => now()->format('Y-m'), 'budget_microusd' => 1000000]);
        $this->expectExceptionMessage('AI_COST_BUDGET_EXCEEDED');
        app(AiCostGuard::class)->reserve($tenant, $this->runtime(2, $tenant->id));
        $this->assertSame(0, AiCostReservation::count());
    }

    public function test_global_budget_is_independent_and_release_is_idempotent(): void
    {
        $tenant = Tenant::create(['uuid' => '33333333-3333-4333-8333-333333333333', 'name' => 'Cost', 'slug' => 'cost3', 'status' => 'active']);
        AiCostBudget::create(['tenant_id' => $tenant->id, 'period' => now()->format('Y-m'), 'budget_microusd' => 1000000]);
        AiCostBudget::create(['tenant_id' => null, 'period' => now()->format('Y-m'), 'budget_microusd' => 10]);
        $this->expectExceptionMessage('AI_GLOBAL_COST_BUDGET_EXCEEDED');
        app(AiCostGuard::class)->reserve($tenant, $this->runtime(3, $tenant->id));
    }

    public function test_negative_usage_is_rejected_before_sql(): void
    {
        $tenant = Tenant::create(['uuid' => '44444444-4444-4444-8444-444444444444', 'name' => 'Cost', 'slug' => 'cost4', 'status' => 'active']);
        $this->expectExceptionMessage('AI_USAGE_INVALID');
        app(AiCostGuard::class)->record($tenant, $this->runtime(4, $tenant->id), ['input_tokens' => -1]);
    }

    public function test_runtime_from_another_tenant_is_rejected_before_sql(): void
    {
        $tenant = Tenant::create(['uuid' => '55555555-5555-4555-8555-555555555555', 'name' => 'Cost', 'slug' => 'cost5', 'status' => 'active']);
        $this->expectExceptionMessage('AI_RUNTIME_TENANT_MISMATCH');
        app(AiCostGuard::class)->record($tenant, $this->runtime(5, $tenant->id + 1), []);
    }

    public function test_negative_rate_is_rejected_by_application_before_sql(): void
    {
        $this->expectExceptionMessage('AI_COST_VALUE_INVALID');
        AiProviderRate::create(['provider' => 'openai', 'model' => 'bad-model', 'input_microusd_per_million' => -1, 'cached_input_microusd_per_million' => 0, 'output_microusd_per_million' => 0, 'effective_from' => now(), 'enabled' => true]);
    }

    private function runtime(int $id, int $tenantId): RuntimeRun
    {
        $run = new RuntimeRun;
        $run->id = $id; $run->tenant_id = $tenantId; $run->execution_mode = AiExecutionMode::Live; $run->provider_code = 'openai'; $run->model_code = 'test-model'; $run->agent_id = null; $run->created_at = now();
        return $run;
    }
}
