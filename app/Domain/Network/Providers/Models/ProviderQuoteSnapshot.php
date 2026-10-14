<?php

namespace App\Domain\Network\Providers\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class ProviderQuoteSnapshot extends Model
{
    protected $table = 'network_provider_quote_snapshots';

    protected $guarded = ['*'];

    protected $hidden = ['id', 'b2c_cotizacion_id', 'consumer_tenant_id', 'provider_tenant_id', 'provider_connection_id',
        'local_shipping_service_id', 'local_shipping_pricing_rule_id', 'commercial_pricing_rule_id',
        'owner_binding', 'provider_cost', 'profit_amount', 'tariff_snapshot', 'commercial_rule_snapshot'];

    protected $casts = ['package_snapshot' => 'array', 'origin_snapshot' => 'array', 'destination_snapshot' => 'array',
        'service_snapshot' => 'array', 'tariff_snapshot' => 'array', 'commercial_rule_snapshot' => 'array',
        'provider_cost' => 'decimal:2', 'subtotal_amount' => 'decimal:2', 'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2', 'profit_amount' => 'decimal:2', 'tax_rate' => 'decimal:4',
        'valid_from' => 'immutable_datetime', 'expires_at' => 'immutable_datetime'];

    protected static function booted(): void
    {
        self::creating(fn (self $s) => $s->uuid ??= (string) Str::uuid());
        self::updating(fn () => throw new \LogicException('Provider quote snapshots are immutable.'));
        self::deleting(function (self $snapshot): void {
            if (ProviderContract::where('quote_snapshot_id', $snapshot->id)->exists()) {
                throw new \LogicException('Selected provider snapshots are immutable.');
            }
        });
    }

    public function newEloquentBuilder($query)
    {
        return new ImmutableProviderBuilder($query);
    }

    protected function performUpdate(Builder $query)
    {
        throw new \LogicException('Provider history is immutable.');
    }

    protected function performDeleteOnModel()
    {
        // FK protection also covers concurrent acceptance during cleanup.
        if (ProviderContract::where('quote_snapshot_id', $this->getKey())->exists()) {
            throw new \LogicException('Selected provider snapshots are immutable.');
        }
        $this->setKeysForSaveQuery($this->newModelQuery())->toBase()->delete();
        $this->exists = false;
    }

    public function publicMetadata(): array
    {
        return ['commercial_breakdown' => ['base' => (float) $this->subtotal_amount, 'area_extendida' => 0,
            'kg_extra' => 0, 'seguro' => 0, 'otros' => 0], 'commercial_subtotal' => (float) $this->subtotal_amount,
            'vat' => (float) $this->tax_amount, 'customer_total' => (float) $this->total_amount,
            'estimated_delivery_date' => null, 'periodicity_name' => '', 'operating_days' => [],
            'zone_code' => '', 'is_reexpedition' => false, 'entrega' => $this->service_snapshot['delivery'] ?? null];
    }

    public function option(): array
    {
        return array_merge($this->publicMetadata(), ['provider_source' => $this->quote_source,
            'provider' => 'tenant_provider', 'capability' => $this->capability,
            'provider_snapshot_uuid' => $this->uuid, 'selection_enabled' => true,
            'contract_selected' => true,
            'logistico' => $this->service_snapshot['provider_name'], 'servicio' => $this->service_snapshot['name'],
            'service_code' => $this->service_snapshot['code'], 'carrier' => $this->service_snapshot['carrier'],
            'logo' => ($tenant = \App\Domain\Network\Tenancy\Models\Tenant::find($this->provider_tenant_id)) ? app(\App\Domain\Network\Providers\ProviderPresentation::class)->identity($tenant)['logo'] : null,
            'commercial_price' => (float) $this->total_amount, 'precio' => (float) $this->total_amount,
            'currency' => $this->currency, 'insurance_enabled' => false, 'restriction' => false, 'restriction_description' => '',
            'weight_billable' => (float) $this->package_snapshot['weight'],
            'dimensions' => $this->package_snapshot['dimensions'] ?: 'No aplica']);
    }
}
