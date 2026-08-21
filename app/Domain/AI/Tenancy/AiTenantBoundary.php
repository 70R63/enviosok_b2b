<?php
namespace App\Domain\AI\Tenancy;
use App\Domain\AI\Support\Exceptions\AiTenantContextException;
use App\Domain\AI\Support\Exceptions\AiTenantMismatchException;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Network\Tenancy\TenantContext;
use InvalidArgumentException;
final class AiTenantBoundary
{
    public function __construct(private TenantContext $context) {}
    public function current(): ?Tenant { return $this->context->current(); }
    public function requireTenant(): Tenant
    {
        $tenant = $this->current();
        if (! $tenant || $tenant->status !== 'active') throw new AiTenantContextException('An active tenant context is required by AI Core.');
        return $tenant;
    }
    public function requireTenantId(): int { return (int) $this->requireTenant()->getKey(); }
    public function assertTenantId(int $tenantId): void
    {
        if ($tenantId !== $this->requireTenantId()) throw new AiTenantMismatchException('The AI resource does not belong to the active tenant.');
    }
    public function assertResourceBelongsToCurrentTenant(object|array $resource): void
    {
        $tenantId = is_array($resource) ? ($resource['tenant_id'] ?? null) : ($resource->tenant_id ?? null);
        if (! is_numeric($tenantId)) throw new InvalidArgumentException('The AI resource must expose a tenant_id.');
        $this->assertTenantId((int) $tenantId);
    }
    public function clear(): void { $this->context->clear(); }
}
