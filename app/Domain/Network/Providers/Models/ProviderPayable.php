<?php

namespace App\Domain\Network\Providers\Models;

use App\Domain\Network\Tenancy\Models\Tenant;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class ProviderPayable extends Model
{
    public $timestamps = false;

    protected $table = 'network_provider_payables';

    protected $guarded = ['*'];

    protected $hidden = ['provider_net_amount', 'provider_tax_amount', 'provider_gross_amount', 'consumer_tenant_id', 'provider_tenant_id', 'quote_snapshot_id', 'provider_contract_id'];

    protected $casts = ['provider_net_amount' => 'decimal:2', 'provider_tax_amount' => 'decimal:2', 'provider_gross_amount' => 'decimal:2', 'created_at' => 'immutable_datetime'];

    protected static function booted(): void
    {
        self::creating(fn (self $p) => $p->uuid ??= (string) Str::uuid());
    }

    public function newEloquentBuilder($query)
    {
        return new ImmutableProviderBuilder($query);
    }

    protected function performUpdate(Builder $query)
    {
        throw new \LogicException('Payable history is immutable.');
    }

    protected function performDeleteOnModel()
    {
        throw new \LogicException('Payable history is immutable.');
    }

    public function fulfillment()
    {
        return $this->belongsTo(ProviderFulfillment::class, 'provider_fulfillment_id');
    }

    public function snapshot()
    {
        return $this->belongsTo(ProviderQuoteSnapshot::class, 'quote_snapshot_id');
    }

    public function consumer()
    {
        return $this->belongsTo(Tenant::class, 'consumer_tenant_id');
    }

    public function provider()
    {
        return $this->belongsTo(Tenant::class, 'provider_tenant_id');
    }

    public function settlements()
    {
        return $this->hasMany(ProviderSettlementEntry::class, 'provider_payable_id');
    }

    public function events()
    {
        return $this->hasMany(ProviderPayableEvent::class, 'provider_payable_id');
    }

    public function financialStatus(): string
    {
        return $this->events()->latest('id')->value('status') ?? 'PENDING';
    }

    public function settledAmount(): string
    {
        return (string) $this->settlements()->get(['amount'])->reduce(fn (BigDecimal $total, $entry) => $total->plus($entry->amount), BigDecimal::zero())->toScale(2);
    }

    public function remainingAmount(): string
    {
        return (string) BigDecimal::of($this->provider_net_amount)->minus($this->settledAmount())->toScale(2);
    }
}
