<?php

namespace App\Domain\Network\Providers\Models;

use App\Domain\Network\Channels\B2C\Models\TenantOperation;
use App\Domain\Shipping\Local\Models\LocalShipment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class ProviderFulfillment extends Model
{
    protected $table = 'network_provider_fulfillments';

    protected $guarded = ['*'];

    protected $hidden = ['id', 'provider_contract_id', 'quote_snapshot_id', 'consumer_tenant_id', 'provider_tenant_id', 'tenant_operation_id', 'local_shipment_id', 'operation', 'shipment'];

    protected $casts = ['commercial_confirmed_at' => 'immutable_datetime', 'pickup_requested_at' => 'immutable_datetime'];

    protected static function booted(): void
    {
        self::creating(fn (self $bridge) => $bridge->uuid ??= (string) Str::uuid());
    }

    public function newEloquentBuilder($query)
    {
        return new ImmutableProviderBuilder($query);
    }

    protected function performUpdate(Builder $query)
    {
        throw new \LogicException('Provider fulfillment bridge is immutable.');
    }

    protected function performDeleteOnModel()
    {
        throw new \LogicException('Provider fulfillment bridge is immutable.');
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(LocalShipment::class, 'local_shipment_id');
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(TenantOperation::class, 'tenant_operation_id');
    }
}
