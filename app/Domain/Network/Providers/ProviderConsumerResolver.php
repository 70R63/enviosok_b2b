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
        if (! Schema::hasTable('network_tenants')) {
            return null;
        }

        $host = TenantDomain::normalize(request()->getHost());
        if ($host === config('zigo_domains.portals.b2c.host')) {
            $uuid = trim((string) config('provider_network.corporate_consumer_uuid'));
            $slug = trim((string) config('provider_network.corporate_consumer_slug'));
            $consumer = $uuid !== '' ? Tenant::where('uuid', $uuid)->first() : null;
            if (! $consumer && $slug !== '') {
                $consumer = Tenant::where('slug', $slug)->first();
            }

            return $consumer?->status === 'active' ? $consumer : null;
        }
        if (! Schema::hasTable('network_tenant_domains')) {
            return null;
        }

        return TenantDomain::query()->where('domain', $host)
            ->where('status', 'verified')->whereHas('tenant', fn ($q) => $q->where('status', 'active'))->first()?->tenant;
    }
}
