<?php

namespace App\Domain\Network\Providers\Models;

use App\Domain\ApiHub\Models\TenantApiClient;
use App\Domain\Network\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class ProviderIntegration extends Model
{
    protected $table = 'network_provider_integrations';

    protected $guarded = ['*'];

    protected $casts = ['valid_from' => 'datetime', 'valid_to' => 'datetime'];

    public function operational(): bool
    {
        $c = ProviderConnection::find($this->provider_connection_id);

        return $this->status === 'active' && (! $this->valid_from || $this->valid_from->lte(now())) && (! $this->valid_to || $this->valid_to->gte(now()))
            && $c && $c->capability === 'LOCAL_SHIPPING' && $c->status === 'active' && (! $c->valid_from || $c->valid_from->lte(now())) && (! $c->valid_to || $c->valid_to->gte(now()))
            && TenantApiClient::whereKey($this->api_client_id)->where('status', 'ACTIVE')->exists()
            && Tenant::whereKey($this->provider_tenant_id)->where('status', 'active')->exists();
    }

    protected static function booted(): void
    {
        self::creating(fn (self $m) => $m->uuid ??= (string) Str::uuid());
        self::saving(function (self $m): void {
            if (! in_array($m->status, ['active', 'inactive'], true) || ($m->valid_from && $m->valid_to && $m->valid_from->gt($m->valid_to))) {
                throw new \DomainException('INVALID_PROVIDER_INTEGRATION');
            }
            if ($m->exists && $m->isDirty(['provider_tenant_id', 'api_client_id', 'provider_connection_id', 'uuid'])) {
                throw new \LogicException('Integration ownership is immutable.');
            }
        });
    }
}
