<?php

namespace App\Domain\Network\Providers;

use App\Domain\Network\Operations\NetworkAdminAuditService;
use App\Domain\Network\Providers\Models\ProviderConnection;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Network\Tenancy\TenantContext;
use App\Domain\Shipping\Local\Models\LocalShippingService;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ProviderConnectionService
{
    public function __construct(private TenantContext $context) {}

    /** Network sysadmins have global tenant administration under the existing policy. */
    public function administratorTenants(User $actor): Collection
    {
        $this->authorizeAdministrator($actor);

        return Tenant::orderBy('name')->get();
    }

    public function authorizeAdministrator(User $actor): void
    {
        abort_unless($actor->hasRol('sysadmin'), 403);
    }

    public function administratorTenant(User $actor, string $uuid): Tenant
    {
        $this->authorizeAdministrator($actor);

        return Tenant::where('uuid', $uuid)->firstOrFail();
    }

    public function administratorServices(User $actor, Tenant $provider): Collection
    {
        $this->authorizeAdministrator($actor);

        return LocalShippingService::where('tenant_id', $provider->id)->orderBy('name')->get();
    }

    /** Does not change tenant catalogs, pricing, snapshots or operational ownership. */
    public function saveConfiguration(User $actor, ?ProviderConnection $connection, array $input): ProviderConnection
    {
        $this->authorizeAdministrator($actor);
        $data = Validator::make($input, [
            'consumer_uuid' => 'required|uuid', 'provider_uuid' => 'required|uuid',
            'capability' => 'required|in:LOCAL_SHIPPING', 'status' => 'required|in:active,inactive',
            'valid_from' => 'nullable|date', 'valid_to' => 'nullable|date',
            'services' => 'sometimes|array|max:1000', 'services.*' => 'required|integer|distinct|min:1',
        ])->validate();
        $consumer = $this->administratorTenant($actor, $data['consumer_uuid']);
        $provider = $this->administratorTenant($actor, $data['provider_uuid']);
        if ($consumer->id === $provider->id) {
            throw ValidationException::withMessages(['provider_uuid' => 'Consumer y provider deben ser distintos.']);
        }

        return DB::transaction(function () use ($actor, $connection, $data, $consumer, $provider) {
            Tenant::whereIn('id', [$consumer->id, $provider->id])->orderBy('id')->lockForUpdate()->get();
            $connection = $connection ? ProviderConnection::whereKey($connection->id)->lockForUpdate()->firstOrFail() : null;
            if ($connection && ($connection->consumer_tenant_id !== $consumer->id || $connection->provider_tenant_id !== $provider->id
                || $connection->capability !== $data['capability'])) {
                throw ValidationException::withMessages(['provider_uuid' => 'La identidad de una conexión existente no puede cambiar.']);
            }
            if (! $connection && ProviderConnection::where('consumer_tenant_id', $consumer->id)->where('provider_tenant_id', $provider->id)
                ->where('capability', $data['capability'])->exists()) {
                throw ValidationException::withMessages(['provider_uuid' => 'La relación ya existe; edítala.']);
            }
            $ids = array_map('intval', $data['services'] ?? []);
            $catalog = LocalShippingService::where('tenant_id', $provider->id)->whereIn('id', $ids)->lockForUpdate()->get();
            if ($catalog->count() !== count($ids)) {
                throw ValidationException::withMessages(['services' => 'Selecciona únicamente servicios de este provider.']);
            }
            $connection ??= new ProviderConnection;
            $before = $connection->exists ? $connection->only(['status', 'valid_from', 'valid_to']) + ['services' => DB::table('network_provider_connection_services')->where('connection_id', $connection->id)->pluck('local_shipping_service_id')->all()] : [];
            $connection->forceFill(['consumer_tenant_id' => $consumer->id, 'provider_tenant_id' => $provider->id,
                'capability' => $data['capability'], 'status' => 'inactive', 'valid_from' => $data['valid_from'] ?? null, 'valid_to' => $data['valid_to'] ?? null])->save();
            $grants = DB::table('network_provider_connection_services')->where('connection_id', $connection->id);
            (clone $grants)->whereNotIn('local_shipping_service_id', $ids)->delete();
            foreach ($ids as $id) {
                if (! (clone $grants)->where('local_shipping_service_id', $id)->exists()) {
                    DB::table('network_provider_connection_services')->insert(['connection_id' => $connection->id,
                        'provider_tenant_id' => $provider->id, 'capability' => $data['capability'], 'local_shipping_service_id' => $id,
                        'created_at' => now(), 'updated_at' => now()]);
                }
            }
            if ($data['status'] === 'active') {
                $report = app(ProviderNetworkReadinessService::class)->evaluate($consumer->fresh(), $provider->fresh(), $connection, true);
                if (! $report['ready']) {
                    throw ValidationException::withMessages(['status' => collect($report['checks'])->where('status', 'FAIL')->pluck('message')->all()]);
                }
                $connection->forceFill(['status' => 'active'])->save();
            }
            app(NetworkAdminAuditService::class)->record($actor->id, 'provider_connection.configured', $connection,
                $before, $connection->only(['status', 'valid_from', 'valid_to']) + ['services' => $ids]);

            return $connection;
        }, 3);
    }

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
