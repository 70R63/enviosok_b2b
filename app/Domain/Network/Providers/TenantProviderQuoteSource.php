<?php

namespace App\Domain\Network\Providers;

use App\Domain\Network\Providers\Models\ProviderConnection;
use App\Domain\Network\Tenancy\Models\TenantDomain;
use App\Domain\Network\Tenancy\TenantContext;
use App\Models\B2cCotizacion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class TenantProviderQuoteSource
{
    public function __construct(private TenantContext $context, private ProviderConnectionService $connections,
        private LocalProviderQuoteEvaluator $evaluator) {}

    public function options(B2cCotizacion $quote, string $segment): array
    {
        try {
            if (! Schema::hasTable('network_provider_connections')) {
                return [];
            }
            $consumer = $this->context->current();
            // Corporate requests may not run ResolveNetworkTenant. Read a verified
            // host mapping without changing the request's TenantContext.
            if (! $consumer && Schema::hasTable('network_tenants') && Schema::hasTable('network_tenant_domains')) {
                $consumer = TenantDomain::query()->where('domain', TenantDomain::normalize(request()->getHost()))
                    ->where('status', 'verified')->whereHas('tenant', fn ($q) => $q->where('status', 'active'))->first()?->tenant;
            }
            if (! $consumer) {
                return [];
            }
            $connections = $this->connections->authorizedConnections($consumer, ProviderConnection::LOCAL_SHIPPING);
        } catch (Throwable $exception) {
            Log::warning('Tenant provider quote source unavailable', ['exception' => get_class($exception)]);

            return [];
        }
        $options = [];
        foreach ($connections as $connection) {
            try {
                $options = array_merge($options, $this->evaluator->options($consumer, $connection->provider, $quote, $segment));
            } catch (Throwable $exception) {
                Log::warning('Tenant provider quote unavailable', ['provider_tenant_id' => $connection->provider_tenant_id,
                    'exception' => get_class($exception)]);
            }
        }

        return $options;
    }
}
