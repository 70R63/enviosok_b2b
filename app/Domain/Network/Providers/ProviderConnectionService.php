<?php

namespace App\Domain\Network\Providers;

use App\Domain\Network\Providers\Models\ProviderConnection;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Network\Tenancy\TenantContext;
use App\Domain\Shipping\Local\Models\LocalShippingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class ProviderConnectionService
{
    public function __construct(private TenantContext $context) {}

    /** Trusted domain callers supply persisted tenants resolved server-side. */
    public function authorizedConnections(Tenant $consumer, string $capability): Collection
    {
        return $this->connections($consumer, $capability)->get();
    }

    public function canConsume(Tenant $consumer, Tenant $provider, string $capability): bool
    {
        return $provider->exists && $this->connections($consumer, $capability)
            ->where('provider_tenant_id', $provider->getKey())->exists();
    }

    /** Request-facing entry point: the consumer comes only from TenantContext. */
    public function currentConnections(string $capability): Collection
    {
        $consumer = $this->context->current();

        return $consumer ? $this->authorizedConnections($consumer, $capability) : new Collection;
    }

    /** Internal catalog only; callers must map public fields before serialization. */
    public function authorizedLocalServices(Tenant $consumer, Tenant $provider): Collection
    {
        $at = now();

        return LocalShippingService::query()
            ->whereRaw($provider->exists ? '1 = 1' : '1 = 0')
            ->where('tenant_id', $provider->getKey())
            ->where('status', 'active')->where('published', true)
            ->where(fn (Builder $q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $at))
            ->where(fn (Builder $q) => $q->whereNull('valid_to')->orWhere('valid_to', '>', $at))
            ->whereIn('id', function ($q) use ($consumer, $provider) {
                $q->select('local_shipping_service_id')->from('network_provider_connection_services')
                    ->where('provider_tenant_id', $provider->getKey())
                    ->where('capability', ProviderConnection::LOCAL_SHIPPING)
                    ->whereRaw('LENGTH(capability) = ?', [strlen(ProviderConnection::LOCAL_SHIPPING)])
                    ->whereIn('connection_id', $this->connections($consumer, ProviderConnection::LOCAL_SHIPPING)->select('id'));
            })->get();
    }

    private function connections(Tenant $consumer, string $capability): Builder
    {
        $at = now();

        return ProviderConnection::query()
            ->whereRaw($consumer->exists ? '1 = 1' : '1 = 0')
            ->whereRaw(ProviderConnection::isValidCapability($capability) ? '1 = 1' : '1 = 0')
            ->where('consumer_tenant_id', $consumer->getKey())
            ->whereColumn('consumer_tenant_id', '<>', 'provider_tenant_id')
            ->where('capability', $capability)->where('status', 'active')
            // ascii_bin remains PAD SPACE on older MySQL. Reject trailing-space
            // variants even when rows were written outside model validation.
            ->whereRaw('LENGTH(capability) = ?', [strlen($capability)])
            ->whereRaw('LENGTH(status) = ?', [strlen('active')])
            ->whereHas('consumer', fn (Builder $q) => $q->where('status', 'active'))
            ->whereHas('provider', fn (Builder $q) => $q->where('status', 'active'))
            ->where(fn (Builder $q) => $q->whereNull('valid_from')->orWhereNull('valid_to')
                ->orWhereColumn('valid_from', '<=', 'valid_to'))
            ->where(fn (Builder $q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $at))
            ->where(fn (Builder $q) => $q->whereNull('valid_to')->orWhere('valid_to', '>', $at));
    }
}
