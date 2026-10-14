<?php

namespace App\Domain\Network\Providers;

use App\Domain\ApiHub\Models\TenantApiClient;
use App\Domain\ApiHub\Models\TenantApiKey;
use App\Domain\Network\Providers\Models\ProviderConnection;
use App\Domain\Network\Providers\Models\ProviderFulfillment;
use App\Domain\Network\Providers\Models\ProviderIntegration;
use App\Domain\Network\Providers\Models\ProviderQuoteSnapshot;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Shipping\Local\Models\LocalShipment;
use Illuminate\Support\Facades\DB;

final class ProviderApiPrincipal
{
    public function __construct(private int $integrationId, private int $keyId) {}

    public function keyId(): int
    {
        return $this->keyId;
    }

    public function integration(bool $lock = false): ProviderIntegration
    {
        $query = ProviderIntegration::whereKey($this->integrationId);
        if ($lock) {
            $query->lockForUpdate();
        }
        $i = $query->firstOrFail();
        $keyQuery = TenantApiKey::whereKey($this->keyId);
        if ($lock) {
            $keyQuery->lockForUpdate();
        } $key = $keyQuery->firstOrFail();
        $clientQuery = TenantApiClient::whereKey($i->api_client_id);
        $connectionQuery = ProviderConnection::whereKey($i->provider_connection_id);
        if ($lock) {
            $clientQuery->lockForUpdate();
            $connectionQuery->lockForUpdate();
        }
        $client = $clientQuery->firstOrFail();
        $c = $connectionQuery->firstOrFail();
        abort_unless($i->status === 'active' && (! $i->valid_from || $i->valid_from->lte(now())) && (! $i->valid_to || $i->valid_to->gte(now()))
            && (int) $key->api_client_id === (int) $i->api_client_id && (int) $key->tenant_id === (int) $i->provider_tenant_id
            && ! $key->revoked_at && (! $key->expires_at || $key->expires_at->isFuture()) && $client->status === 'ACTIVE'
            && (int) $client->tenant_id === (int) $i->provider_tenant_id && Tenant::whereKey($i->provider_tenant_id)->where('status', 'active')->exists()
            && $c->status === 'active' && $c->capability === 'LOCAL_SHIPPING' && (! $c->valid_from || $c->valid_from->lte(now())) && (! $c->valid_to || $c->valid_to->gte(now())), 403);

        return $i;
    }

    public function authorize(string $scope, ?LocalShipment $shipment = null): ProviderIntegration
    {
        $i = $this->integration(DB::transactionLevel() > 0);
        abort_unless(in_array($scope, TenantApiKey::findOrFail($this->keyId)->scopes ?? [], true), 403);
        if ($shipment) {
            $b = ProviderFulfillment::where('local_shipment_id', $shipment->id)->where('provider_tenant_id', $i->provider_tenant_id)->firstOrFail();
            $this->owns($b, $i);
        }

        return $i;
    }

    public function fulfillment(string $uuid): ProviderFulfillment
    {
        $i = $this->integration();
        $b = ProviderFulfillment::where('uuid', $uuid)->where('provider_tenant_id', $i->provider_tenant_id)->firstOrFail();
        $this->owns($b, $i);

        return $b;
    }

    private function owns(ProviderFulfillment $b, ProviderIntegration $i): void
    {
        abort_unless(ProviderQuoteSnapshot::whereKey($b->quote_snapshot_id)->where('provider_connection_id', $i->provider_connection_id)->exists(), 404);
    }

    public function clientId(): int
    {
        return (int) $this->integration()->api_client_id;
    }

    public function metadata(): array
    {
        return ['channel' => 'CRM_API', 'api_client_uuid' => TenantApiClient::findOrFail($this->clientId())->uuid];
    }
}
