<?php

namespace Tests\Feature;

use App\Domain\AI\Jobs\TenantAwareAiJob;
use App\Domain\AI\Core\AiFeatureGate;
use App\Domain\AI\Providers\ProviderRegistry;
use App\Domain\AI\Runtime\Contracts\EmbeddingGateway;
use App\Domain\AI\Runtime\Contracts\ModelGateway;
use App\Domain\AI\Support\Exceptions\AiDisabledException;
use App\Domain\AI\Support\Exceptions\AiTenantContextException;
use App\Domain\AI\Support\Exceptions\AiTenantMismatchException;
use App\Domain\AI\Support\Exceptions\InvalidKnowledgeDiskException;
use App\Domain\AI\Support\KnowledgeStorageGuard;
use App\Domain\AI\Tenancy\AiTenantBoundary;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Network\Tenancy\TenantContext;
use Illuminate\Container\Container;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use InvalidArgumentException;
use Tests\TestCase;

final class AiCoreFoundationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.enabled' => true, 'ai.knowledge.disk' => 'local', 'ai.default_provider' => null]);
        Schema::dropIfExists('network_tenants');
        Schema::create('network_tenants', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status');
            $table->unsignedBigInteger('current_plan_id')->nullable();
            $table->timestamps();
        });
        app(TenantContext::class)->clear();
        RecordingAiJob::reset();
    }

    public function test_ai_boundary_fails_without_tenant_context(): void
    {
        $this->expectException(AiTenantContextException::class);
        app(AiTenantBoundary::class)->requireTenant();
    }

    public function test_ai_boundary_rejects_a_different_tenant(): void
    {
        [$first, $second] = [$this->tenant('first'), $this->tenant('second')];
        app(TenantContext::class)->set($first);
        $this->expectException(AiTenantMismatchException::class);
        app(AiTenantBoundary::class)->assertResourceBelongsToCurrentTenant(['tenant_id' => $second->id]);
    }

    public function test_ai_boundary_accepts_the_active_tenant_resource(): void
    {
        $tenant = $this->tenant('correct');
        app(TenantContext::class)->set($tenant);
        app(AiTenantBoundary::class)->assertResourceBelongsToCurrentTenant((object) ['tenant_id' => $tenant->id]);
        $this->assertSame($tenant->id, app(AiTenantBoundary::class)->requireTenantId());
    }

    public function test_ai_boundary_rejects_resource_without_tenant_id(): void
    {
        app(TenantContext::class)->set($this->tenant('missing-resource-tenant'));
        $this->expectException(InvalidArgumentException::class);
        app(AiTenantBoundary::class)->assertResourceBelongsToCurrentTenant((object) ['id' => 10]);
    }

    public function test_feature_gate_parses_supported_values_and_fails_closed(): void
    {
        $gate = app(AiFeatureGate::class);
        foreach ([[false, false], [true, true], [0, false], [1, true], ['false', false], ['true', true], ['invalid', false]] as [$value, $expected]) {
            config(['ai.enabled' => $value]);
            $this->assertSame($expected, $gate->enabled(), 'Unexpected result for '.var_export($value, true));
        }
    }

    public function test_ai_job_restores_then_clears_the_correct_tenant(): void
    {
        $tenant = $this->tenant('job');
        $stale = $this->tenant('stale');
        app(TenantContext::class)->set($stale);
        $job = new RecordingAiJob($tenant->id);
        $this->app->call([$job, 'handle']);
        $this->assertSame($tenant->id, RecordingAiJob::$observedTenantId);
        $this->assertNull(app(TenantContext::class)->current());
        $this->assertSame('database', $job->connection);
        $this->assertSame('ai', $job->queue);
    }

    public function test_ai_job_clears_context_when_execution_throws(): void
    {
        $tenant = $this->tenant('exception');
        try {
            $this->app->call([new RecordingAiJob($tenant->id, true), 'handle']);
            $this->fail('The probe job should throw.');
        } catch (RuntimeException $exception) {
            $this->assertSame('probe failure', $exception->getMessage());
        }
        $this->assertNull(app(TenantContext::class)->current());
    }

    public function test_serialized_ai_job_restores_correct_tenant_and_clears_context(): void
    {
        $tenant = $this->tenant('serialized-success');
        $stale = $this->tenant('serialized-stale');
        app(TenantContext::class)->set($stale);
        $restored = unserialize(serialize(new RecordingAiJob($tenant->id)));
        $this->assertInstanceOf(RecordingAiJob::class, $restored);
        $this->assertSame($tenant->id, $restored->tenantId());
        $this->app->call([$restored, 'handle']);
        $this->assertSame($tenant->id, RecordingAiJob::$observedTenantId);
        $this->assertNull(app(TenantContext::class)->current());
    }

    public function test_serialized_ai_job_clears_context_when_payload_throws(): void
    {
        $tenant = $this->tenant('serialized-exception');
        $restored = unserialize(serialize(new RecordingAiJob($tenant->id, true)));
        try {
            $this->app->call([$restored, 'handle']);
            $this->fail('The serialized probe job should throw.');
        } catch (RuntimeException $exception) {
            $this->assertSame('probe failure', $exception->getMessage());
        }
        $this->assertSame($tenant->id, RecordingAiJob::$observedTenantId);
        $this->assertNull(app(TenantContext::class)->current());
    }

    public function test_serialized_job_rejects_missing_and_inactive_tenant(): void
    {
        $inactive = $this->tenant('serialized-inactive', 'inactive');
        foreach ([$inactive->id, 999999] as $tenantId) {
            $restored = unserialize(serialize(new RecordingAiJob($tenantId)));
            try {
                $this->app->call([$restored, 'handle']);
                $this->fail('Invalid serialized tenant should fail.');
            } catch (AiTenantContextException) {
                $this->assertNull(app(TenantContext::class)->current());
            }
        }
        $this->assertFalse(RecordingAiJob::$executed);
    }

    public function test_missing_or_inactive_job_tenant_fails_closed(): void
    {
        $inactive = $this->tenant('inactive', 'inactive');
        foreach ([$inactive->id, 999999] as $tenantId) {
            try {
                $this->app->call([new RecordingAiJob($tenantId), 'handle']);
                $this->fail('Invalid tenant should fail.');
            } catch (AiTenantContextException) {
                $this->assertNull(app(TenantContext::class)->current());
            }
        }
        $this->assertFalse(RecordingAiJob::$executed);
    }

    public function test_public_knowledge_disk_is_rejected(): void
    {
        config(['ai.knowledge.disk' => 'public']);
        $this->expectException(InvalidKnowledgeDiskException::class);
        app(KnowledgeStorageGuard::class)->disk();
    }

    public function test_knowledge_storage_accepts_private_local_and_s3_and_rejects_unsafe_disks(): void
    {
        $guard = app(KnowledgeStorageGuard::class);
        config(['ai.knowledge.disk' => 'local']);
        $this->assertSame('local', $guard->disk());
        config(['filesystems.disks.ai_private_s3' => ['driver' => 's3'], 'ai.knowledge.disk' => 'ai_private_s3']);
        $this->assertSame('ai_private_s3', $guard->disk());

        foreach (['missing_ai_disk', 'public', 'ai_public_s3'] as $disk) {
            if ($disk === 'ai_public_s3') config(['filesystems.disks.ai_public_s3' => ['driver' => 's3', 'visibility' => 'public']]);
            config(['ai.knowledge.disk' => $disk]);
            try {
                $guard->disk();
                $this->fail("Unsafe knowledge disk {$disk} should fail.");
            } catch (InvalidKnowledgeDiskException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_disabled_ai_prevents_job_execution(): void
    {
        config(['ai.enabled' => false]);
        $tenant = $this->tenant('disabled');
        try {
            $this->app->call([new RecordingAiJob($tenant->id), 'handle']);
            $this->fail('Disabled AI should fail.');
        } catch (AiDisabledException) {
            $this->assertFalse(RecordingAiJob::$executed);
            $this->assertNull(app(TenantContext::class)->current());
        }
    }

    public function test_provider_contracts_have_no_real_bindings(): void
    {
        $this->assertFalse($this->app->bound(ModelGateway::class));
        $this->assertFalse($this->app->bound(EmbeddingGateway::class));
        $this->expectException(\App\Domain\AI\Runtime\Exceptions\ModelProviderNotConfiguredException::class);
        app(ProviderRegistry::class)->model();
    }

    public function test_provider_registry_is_blocked_when_ai_is_disabled(): void
    {
        config(['ai.enabled' => false]);
        $this->expectException(AiDisabledException::class);
        app(ProviderRegistry::class)->model();
    }

    private function tenant(string $slug, string $status = 'active'): Tenant
    {
        return Tenant::create(['name' => ucfirst($slug), 'slug' => $slug, 'status' => $status]);
    }
}

final class RecordingAiJob extends TenantAwareAiJob
{
    public static bool $executed = false;
    public static ?int $observedTenantId = null;

    public function __construct(int $tenantId, private bool $shouldFail = false)
    {
        parent::__construct($tenantId);
    }

    public static function reset(): void
    {
        self::$executed = false;
        self::$observedTenantId = null;
    }

    protected function execute(Container $container): mixed
    {
        self::$executed = true;
        self::$observedTenantId = $container->make(TenantContext::class)->id();
        if ($this->shouldFail) throw new RuntimeException('probe failure');
        return null;
    }
}
