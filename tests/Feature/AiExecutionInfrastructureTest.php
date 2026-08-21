<?php
namespace Tests\Feature;
use App\Domain\AI\Core\AiEntitlementGate;
use App\Domain\AI\Jobs\AiJobDispatcher;
use App\Domain\AI\Jobs\TenantAwareAiJob;
use App\Domain\AI\Support\Exceptions\AiDisabledException;
use App\Domain\AI\Support\Exceptions\AiEntitlementException;
use App\Domain\AI\Support\Exceptions\AiTenantContextException;
use App\Domain\AI\Support\Exceptions\AiTenantMismatchException;
use App\Domain\AI\Support\Exceptions\InvalidKnowledgeDiskException;
use App\Domain\AI\Support\Exceptions\PermanentAiJobException;
use App\Domain\AI\Support\Exceptions\RetryableAiJobException;
use App\Domain\AI\Support\KnowledgeStorageGuard;
use App\Domain\AI\Support\TenantKnowledgeStorage;
use App\Domain\Network\Billing\Models\Entitlement;
use App\Domain\Network\Billing\Models\Subscription;
use App\Domain\Network\Catalog\Models\Module;
use App\Domain\Network\Catalog\Models\Plan;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Network\Tenancy\TenantContext;
use Illuminate\Container\Container;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

final class AiExecutionInfrastructureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'ai.enabled' => true,
            'ai.entitlement.module_code' => 'AI_CORE',
            'ai.queue.connection' => 'database',
            'ai.queue.name' => 'ai',
            'ai.max_attempts' => 3,
            'ai.job_timeout' => 120,
            'ai.job_backoff_seconds' => '10,30,60',
            'ai.knowledge.disk' => 'ai_test',
            'filesystems.disks.ai_test' => ['driver' => 'local', 'root' => storage_path('framework/testing/disks/ai_test')],
        ]);
        $this->schema();
        app(TenantContext::class)->clear();
        Bus::fake();
    }

    public function test_dispatcher_fails_when_ai_is_disabled(): void
    {
        config(['ai.enabled' => false]);
        $this->expectException(AiDisabledException::class);
        app(AiJobDispatcher::class)->dispatch(fn (int $tenantId) => new InfrastructureAiJob($tenantId));
    }

    public function test_dispatcher_fails_without_tenant_context(): void
    {
        $this->expectException(AiTenantContextException::class);
        app(AiJobDispatcher::class)->dispatch(fn (int $tenantId) => new InfrastructureAiJob($tenantId));
    }

    public function test_dispatcher_fails_for_inactive_tenant(): void
    {
        app(TenantContext::class)->set($this->tenant('inactive', 'inactive'));
        $this->expectException(AiTenantContextException::class);
        app(AiJobDispatcher::class)->dispatch(fn (int $tenantId) => new InfrastructureAiJob($tenantId));
    }

    public function test_dispatcher_fails_without_active_subscription_or_entitlement(): void
    {
        $tenant = $this->tenant('not-entitled');
        app(TenantContext::class)->set($tenant);
        $this->expectException(AiEntitlementException::class);
        app(AiJobDispatcher::class)->dispatch(fn (int $tenantId) => new InfrastructureAiJob($tenantId));
    }

    public function test_dispatcher_fails_when_active_subscription_lacks_ai_core(): void
    {
        $tenant = $this->tenant('missing-entitlement');
        $this->activeSubscription($tenant);
        app(TenantContext::class)->set($tenant);
        $this->expectException(AiEntitlementException::class);
        app(AiJobDispatcher::class)->dispatch(fn (int $tenantId) => new InfrastructureAiJob($tenantId));
    }

    public function test_dispatcher_derives_tenant_and_bus_receives_configured_job(): void
    {
        $tenant = $this->entitledTenant('allowed');
        app(TenantContext::class)->set($tenant);
        $factoryTenant = null;
        app(AiJobDispatcher::class)->dispatch(function (int $tenantId) use (&$factoryTenant) {
            $factoryTenant = $tenantId;
            return new InfrastructureAiJob($tenantId);
        });
        $this->assertSame($tenant->id, $factoryTenant);
        Bus::assertDispatched(InfrastructureAiJob::class, fn ($job) => $job->tenantId() === $tenant->id && $job->connection === 'database' && $job->queue === 'ai');
    }

    public function test_dispatcher_rejects_job_for_different_tenant_before_bus(): void
    {
        $tenant = $this->entitledTenant('mismatch-owner');
        $other = $this->tenant('mismatch-other');
        app(TenantContext::class)->set($tenant);
        try {
            app(AiJobDispatcher::class)->dispatch(fn (int $tenantId) => new InfrastructureAiJob($other->id));
            $this->fail('Mismatched AI job should fail.');
        } catch (AiTenantMismatchException) {
            Bus::assertNotDispatched(InfrastructureAiJob::class);
        }
    }

    public function test_job_policies_use_ai_configuration_and_safe_defaults(): void
    {
        config(['ai.queue.connection' => 'redis_ai', 'ai.queue.name' => 'critical-ai', 'ai.max_attempts' => 5, 'ai.job_timeout' => 240, 'ai.job_backoff_seconds' => '5,15,45']);
        $job = new InfrastructureAiJob(1);
        $this->assertSame('redis_ai', $job->connection);
        $this->assertSame('critical-ai', $job->queue);
        $this->assertSame(5, $job->tries);
        $this->assertSame(240, $job->timeout);
        $this->assertSame([5, 15, 45], $job->backoff());

        config(['ai.queue.connection' => '', 'ai.queue.name' => '../bad', 'ai.max_attempts' => 0, 'ai.job_timeout' => 99999, 'ai.job_backoff_seconds' => 'bad,-1']);
        $safe = new InfrastructureAiJob(1);
        $this->assertSame('database', $safe->connection);
        $this->assertSame('ai', $safe->queue);
        $this->assertSame(3, $safe->tries);
        $this->assertSame(120, $safe->timeout);
        $this->assertSame([10, 30, 60], $safe->backoff());
    }

    public function test_ai_job_exceptions_are_distinguishable(): void
    {
        $this->assertNotSame(RetryableAiJobException::class, PermanentAiJobException::class);
        $this->assertInstanceOf(\RuntimeException::class, new RetryableAiJobException());
        $this->assertInstanceOf(\RuntimeException::class, new PermanentAiJobException());
    }

    public function test_laravel_failed_callback_clears_tenant_context(): void
    {
        app(TenantContext::class)->set($this->tenant('failed-callback'));
        (new InfrastructureAiJob(1))->failed(new PermanentAiJobException('failed'));
        $this->assertNull(app(TenantContext::class)->current());
    }

    public function test_storage_uses_distinct_immutable_tenant_prefixes(): void
    {
        [$first, $second] = [$this->tenant('storage-a'), $this->tenant('storage-b')];
        app(TenantContext::class)->set($first);
        $firstPath = app(TenantKnowledgeStorage::class)->path('faq/source.txt');
        app(TenantContext::class)->set($second);
        $secondPath = app(TenantKnowledgeStorage::class)->path('faq/source.txt');
        $this->assertSame("tenants/{$first->id}/ai/knowledge/faq/source.txt", $firstPath);
        $this->assertSame("tenants/{$second->id}/ai/knowledge/faq/source.txt", $secondPath);
        $this->assertNotSame($firstPath, $secondPath);
    }

    public function test_storage_cannot_escape_or_build_another_tenant_prefix(): void
    {
        $tenant = $this->tenant('path-owner');
        $other = $this->tenant('path-other');
        app(TenantContext::class)->set($tenant);
        $nested = app(TenantKnowledgeStorage::class)->path("tenants/{$other->id}/ai/knowledge/file.txt");
        $this->assertStringStartsWith("tenants/{$tenant->id}/ai/knowledge/", $nested);
        $this->assertNotSame("tenants/{$other->id}/ai/knowledge/file.txt", $nested);

        foreach (['', '../secret', 'folder/../secret', '/absolute', 'C:/absolute', 'folder\\file', "control\0file", 'folder//file', './file'] as $unsafe) {
            try {
                app(TenantKnowledgeStorage::class)->path($unsafe);
                $this->fail("Unsafe path should fail: {$unsafe}");
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_private_local_storage_operations_remain_in_tenant_prefix(): void
    {
        Storage::fake('ai_test');
        $tenant = $this->tenant('files');
        app(TenantContext::class)->set($tenant);
        $storage = app(TenantKnowledgeStorage::class);
        $this->assertTrue($storage->put('faq/a.txt', 'private content'));
        $this->assertTrue($storage->exists('faq/a.txt'));
        $this->assertSame('private content', $storage->get('faq/a.txt'));
        Storage::disk('ai_test')->assertExists("tenants/{$tenant->id}/ai/knowledge/faq/a.txt");
        $this->assertTrue($storage->delete('faq/a.txt'));
        $this->assertFalse($storage->exists('faq/a.txt'));
    }

    public function test_public_disk_is_blocked_and_private_s3_validates_without_network(): void
    {
        $guard = app(KnowledgeStorageGuard::class);
        config(['ai.knowledge.disk' => 'public']);
        try { $guard->disk(); $this->fail('Public disk should fail.'); } catch (InvalidKnowledgeDiskException) { $this->assertTrue(true); }
        config(['filesystems.disks.ai_s3' => ['driver' => 's3'], 'ai.knowledge.disk' => 'ai_s3']);
        $this->assertSame('ai_s3', $guard->disk());
    }

    private function tenant(string $slug, string $status = 'active'): Tenant
    {
        return Tenant::create(['name' => ucfirst($slug), 'slug' => $slug, 'status' => $status]);
    }

    private function entitledTenant(string $slug): Tenant
    {
        $tenant = $this->tenant($slug);
        $subscription = $this->activeSubscription($tenant);
        $module = Module::create(['code' => 'AI_CORE', 'name' => 'AI Core', 'type' => 'core', 'is_active' => true, 'sort_order' => 1]);
        Entitlement::create(['subscription_id' => $subscription->id, 'tenant_id' => $tenant->id, 'module_id' => $module->id, 'code' => 'AI_CORE', 'is_enabled' => true, 'source' => 'plan']);
        return $tenant;
    }

    private function activeSubscription(Tenant $tenant): Subscription
    {
        $plan = Plan::create(['code' => 'PLAN_'.$tenant->id, 'name' => 'AI Plan', 'status' => 'active', 'currency' => 'MXN']);
        return Subscription::create(['tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'status' => 'active', 'started_at' => now()->subDay(), 'current_period_start' => now()->subDay(), 'current_period_end' => now()->addMonth()]);
    }

    private function schema(): void
    {
        foreach (['network_entitlements', 'network_subscriptions', 'network_plan_modules', 'network_modules', 'network_plans', 'network_tenants'] as $table) Schema::dropIfExists($table);
        Schema::create('network_tenants', function (Blueprint $t) { $t->id(); $t->uuid('uuid')->unique(); $t->string('name'); $t->string('slug')->unique(); $t->string('status'); $t->unsignedBigInteger('current_plan_id')->nullable(); $t->timestamps(); });
        Schema::create('network_plans', function (Blueprint $t) { $t->id(); $t->string('code')->unique(); $t->string('name'); $t->text('description')->nullable(); $t->string('status'); $t->decimal('monthly_price', 12, 2)->nullable(); $t->decimal('annual_price', 12, 2)->nullable(); $t->char('currency', 3); $t->unsignedInteger('included_operations')->nullable(); $t->timestamps(); });
        Schema::create('network_modules', function (Blueprint $t) { $t->id(); $t->string('code')->unique(); $t->string('name'); $t->text('description')->nullable(); $t->string('type'); $t->boolean('is_active'); $t->unsignedSmallInteger('sort_order'); $t->timestamps(); });
        Schema::create('network_plan_modules', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('plan_id'); $t->unsignedBigInteger('module_id'); $t->boolean('is_included'); $t->unsignedInteger('limit_value')->nullable(); $t->timestamps(); });
        Schema::create('network_subscriptions', function (Blueprint $t) { $t->id(); $t->uuid('uuid')->unique(); $t->unsignedBigInteger('tenant_id'); $t->unsignedBigInteger('plan_id'); $t->string('status'); $t->unsignedInteger('operations_limit')->nullable(); foreach (['started_at', 'current_period_start', 'current_period_end', 'trial_ends_at', 'grace_ends_at', 'canceled_at', 'ended_at'] as $column) $t->timestamp($column)->nullable(); $t->timestamps(); });
        Schema::create('network_entitlements', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('subscription_id'); $t->unsignedBigInteger('tenant_id'); $t->unsignedBigInteger('module_id'); $t->string('code'); $t->boolean('is_enabled'); $t->unsignedInteger('limit_value')->nullable(); $t->string('source'); $t->timestamps(); });
    }
}

final class InfrastructureAiJob extends TenantAwareAiJob
{
    protected function execute(Container $container): mixed { return null; }
}
