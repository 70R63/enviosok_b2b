<?php

namespace Tests\Feature;

use App\Domain\Network\Billing\Models\{Entitlement, Subscription};
use App\Domain\Network\Catalog\Models\{Module, Plan};
use App\Domain\Network\Tenancy\Models\Tenant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

final class NormalizeNetworkEntitlementsCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            $this->markTestSkipped('Requires SQLite :memory:.');
        }
        $this->schema();
    }

    public function test_dry_run_reports_without_modifying_database_and_handles_tenant_without_subscription(): void
    {
        [$plan, $tenant] = $this->legacyTenant('dry-run', ['WHITE_LABEL', 'CUSTOMERS', 'QUOTES', 'SHIPPING']);
        Tenant::create(['name' => 'Sin suscripción', 'slug' => 'without-subscription', 'status' => 'active', 'current_plan_id' => $plan->id]);
        $before = [DB::table('network_plan_modules')->count(), Entitlement::count(), Module::count()];

        $this->artisan('zigo:network:normalize-entitlements')
            ->expectsOutputToContain('MODE=DRY-RUN')->expectsOutputToContain('WOULD_ADD')
            ->expectsOutputToContain('NO_CURRENT_SUBSCRIPTION')->assertExitCode(0);

        $this->assertSame($before, [DB::table('network_plan_modules')->count(), Entitlement::count(), Module::count()]);
        $this->assertFalse($plan->modules()->where('code', 'B2C')->exists());
        $this->assertSame('dry-run', $tenant->slug);
    }

    public function test_apply_adds_b2c_and_only_plan_backed_canonical_snapshots_and_is_idempotent(): void
    {
        [$legacyPlan, $legacyTenant, $legacySubscription] = $this->legacyTenant('legacy', ['WHITE_LABEL', 'CUSTOMERS', 'QUOTES', 'SHIPPING']);
        [$driverPlan, $driverTenant, $driverSubscription] = $this->legacyTenant('driver-plan', ['B2C', 'TRACKING', 'DRIVER']);
        $legacyCodesBefore = $legacyPlan->modules()->pluck('code')->all();

        $this->artisan('zigo:network:normalize-entitlements', ['--apply' => true])
            ->expectsOutputToContain('MODE=APPLY')->expectsOutputToContain('ADDED')->assertExitCode(0);

        $this->assertTrue($legacyPlan->modules()->where('code', 'B2C')->wherePivot('is_included', true)->exists());
        $this->assertSameCanonicalEntitlement($legacyTenant, $legacySubscription, 'B2C');
        $this->assertSameCanonicalEntitlement($legacyTenant, $legacySubscription, 'SHIPPING');
        $this->assertDatabaseMissing('network_entitlements', ['subscription_id' => $legacySubscription->id, 'code' => 'TRACKING']);
        $this->assertDatabaseMissing('network_entitlements', ['subscription_id' => $legacySubscription->id, 'code' => 'DRIVER']);
        $this->assertSameCanonicalEntitlement($driverTenant, $driverSubscription, 'B2C');
        $this->assertSameCanonicalEntitlement($driverTenant, $driverSubscription, 'TRACKING');
        $this->assertSameCanonicalEntitlement($driverTenant, $driverSubscription, 'DRIVER');
        foreach ($legacyCodesBefore as $legacyCode) $this->assertTrue($legacyPlan->modules()->where('code', $legacyCode)->exists());
        $this->assertSame(0, Entitlement::where('subscription_id', $legacySubscription->id)->where('tenant_id', $driverTenant->id)->count());

        $counts = [DB::table('network_plan_modules')->count(), Entitlement::count(), Module::count()];
        $this->artisan('zigo:network:normalize-entitlements', ['--apply' => true])
            ->expectsOutputToContain('Cambios aplicados: 0')->assertExitCode(0);
        $this->assertSame($counts, [DB::table('network_plan_modules')->count(), Entitlement::count(), Module::count()]);
    }

    private function legacyTenant(string $slug, array $codes): array
    {
        $plan = Plan::create(['code' => strtoupper($slug), 'name' => ucfirst($slug), 'status' => 'active', 'currency' => 'MXN']);
        foreach ($codes as $position => $code) {
            $module = Module::firstOrCreate(['code' => $code], ['name' => $code, 'type' => 'core', 'is_active' => true, 'sort_order' => $position]);
            $plan->modules()->syncWithoutDetaching([$module->id => ['is_included' => true, 'limit_value' => null]]);
        }
        $tenant = Tenant::create(['name' => ucfirst($slug), 'slug' => $slug, 'status' => 'active', 'current_plan_id' => $plan->id]);
        $subscription = Subscription::create(['tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'status' => 'active', 'started_at' => now()->subDay(), 'current_period_start' => now()->subDay(), 'current_period_end' => now()->addMonth()]);
        foreach (array_intersect($codes, ['WHITE_LABEL', 'CUSTOMERS', 'QUOTES']) as $code) {
            $module = Module::where('code', $code)->firstOrFail();
            Entitlement::create(['subscription_id' => $subscription->id, 'tenant_id' => $tenant->id, 'module_id' => $module->id, 'code' => $code, 'is_enabled' => true, 'source' => 'plan']);
        }
        return [$plan, $tenant, $subscription];
    }

    private function assertSameCanonicalEntitlement(Tenant $tenant, Subscription $subscription, string $code): void
    {
        $this->assertDatabaseHas('network_entitlements', ['tenant_id' => $tenant->id, 'subscription_id' => $subscription->id, 'code' => $code, 'is_enabled' => true, 'source' => 'plan']);
    }

    private function schema(): void
    {
        Schema::create('network_modules', fn (Blueprint $t) => [$t->id(), $t->string('code')->unique(), $t->string('name'), $t->text('description')->nullable(), $t->string('type'), $t->boolean('is_active'), $t->unsignedSmallInteger('sort_order'), $t->timestamps()]);
        Schema::create('network_plans', fn (Blueprint $t) => [$t->id(), $t->string('code')->unique(), $t->string('name'), $t->text('description')->nullable(), $t->string('status'), $t->decimal('monthly_price', 12, 2)->nullable(), $t->decimal('annual_price', 12, 2)->nullable(), $t->char('currency', 3), $t->unsignedInteger('included_operations')->nullable(), $t->timestamps()]);
        Schema::create('network_plan_modules', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('plan_id'), $t->unsignedBigInteger('module_id'), $t->boolean('is_included'), $t->unsignedInteger('limit_value')->nullable(), $t->timestamps(), $t->unique(['plan_id', 'module_id'])]);
        Schema::create('network_tenants', fn (Blueprint $t) => [$t->id(), $t->uuid('uuid')->nullable(), $t->string('name'), $t->string('slug'), $t->string('status'), $t->unsignedBigInteger('current_plan_id')->nullable(), $t->timestamps()]);
        Schema::create('network_subscriptions', fn (Blueprint $t) => [$t->id(), $t->uuid('uuid')->unique(), $t->unsignedBigInteger('tenant_id'), $t->unsignedBigInteger('plan_id'), $t->string('status'), $t->unsignedInteger('operations_limit')->nullable(), $t->timestamp('started_at'), $t->timestamp('current_period_start'), $t->timestamp('current_period_end'), $t->timestamp('trial_ends_at')->nullable(), $t->timestamp('grace_ends_at')->nullable(), $t->timestamp('canceled_at')->nullable(), $t->timestamp('ended_at')->nullable(), $t->timestamps()]);
        Schema::create('network_entitlements', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('subscription_id'), $t->unsignedBigInteger('tenant_id'), $t->unsignedBigInteger('module_id'), $t->string('code'), $t->boolean('is_enabled'), $t->unsignedInteger('limit_value')->nullable(), $t->string('source'), $t->timestamps(), $t->unique(['subscription_id', 'module_id']), $t->unique(['subscription_id', 'code'])]);
    }
}
