<?php
namespace App\Domain\AI\Jobs;
use App\Domain\AI\Core\AiFeatureGate;
use App\Domain\AI\Support\Exceptions\AiTenantContextException;
use App\Domain\AI\Tenancy\AiTenantBoundary;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Network\Tenancy\TenantContext;
final class AiTenantJobExecution
{
    public function __construct(private TenantContext $context, private AiTenantBoundary $boundary, private AiFeatureGate $featureGate) {}
    public function run(int $tenantId, callable $callback): mixed
    {
        $this->context->clear();
        try {
            $this->featureGate->ensureEnabled();
            $tenant = Tenant::query()->whereKey($tenantId)->where('status', 'active')->first();
            if (! $tenant) throw new AiTenantContextException('The AI job tenant does not exist or is not active.');
            $this->context->set($tenant);
            $this->boundary->assertTenantId($tenantId);
            return $callback($tenant);
        } finally {
            // A previous worker context is untrusted residue and must never be restored.
            $this->context->clear();
        }
    }
}
