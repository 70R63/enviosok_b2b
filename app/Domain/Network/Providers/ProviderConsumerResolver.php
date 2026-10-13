<?php

namespace App\Domain\Network\Providers;

use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Network\Tenancy\Models\TenantDomain;
use App\Domain\Network\Tenancy\TenantContext;
use Illuminate\Support\Facades\Schema;

class ProviderConsumerResolver
{
    public function __construct(private TenantContext $context) {}

    public function current(): ?Tenant
    {
        if ($consumer = $this->context->current()) {
            return $consumer;
        }
        if (! Schema::hasTable('network_tenants') || ! Schema::hasTable('network_tenant_domains')) {
            return null;
        }

        return TenantDomain::query()->where('domain', TenantDomain::normalize(request()->getHost()))
            ->where('status', 'verified')->whereHas('tenant', fn ($q) => $q->where('status', 'active'))->first()?->tenant;
    }
}
