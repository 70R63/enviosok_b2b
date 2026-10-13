<?php

namespace App\Domain\Network\Providers\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class ProviderContract extends Model
{
    protected $table = 'network_provider_contracts';

    protected $guarded = ['*'];

    protected $hidden = ['id', 'quote_snapshot_id', 'b2c_cotizacion_id', 'selected_by_user_id', 'snapshot'];

    protected $casts = ['selected_at' => 'immutable_datetime'];

    protected static function booted(): void
    {
        self::creating(fn (self $c) => $c->uuid ??= (string) Str::uuid());
        self::updating(fn () => throw new \LogicException('Provider contracts are immutable.'));
        self::deleting(fn () => throw new \LogicException('Provider contracts are immutable.'));
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
        throw new \LogicException('Provider contracts are immutable.');
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(ProviderQuoteSnapshot::class, 'quote_snapshot_id');
    }
}
