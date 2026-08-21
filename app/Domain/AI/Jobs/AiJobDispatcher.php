<?php
namespace App\Domain\AI\Jobs;
use App\Domain\AI\Core\AiEntitlementGate;
use App\Domain\AI\Core\AiFeatureGate;
use App\Domain\AI\Support\Exceptions\AiTenantMismatchException;
use App\Domain\AI\Tenancy\AiTenantBoundary;
use Illuminate\Contracts\Bus\Dispatcher;
use InvalidArgumentException;
final class AiJobDispatcher
{
    public function __construct(
        private Dispatcher $dispatcher,
        private AiFeatureGate $featureGate,
        private AiTenantBoundary $tenants,
        private AiEntitlementGate $entitlements,
    ) {}
    public function dispatch(callable $jobFactory): mixed
    {
        $this->featureGate->ensureEnabled();
        $tenantId = $this->tenants->requireTenantId();
        $this->entitlements->ensureAllowed();
        $job = $jobFactory($tenantId);
        if (! $job instanceof TenantAwareAiJob) throw new InvalidArgumentException('AI job factories must return a TenantAwareAiJob.');
        if ($job->tenantId() !== $tenantId) throw new AiTenantMismatchException('The AI job tenant does not match the active tenant.');
        return $this->dispatcher->dispatch($job);
    }
}
