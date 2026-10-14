<?php

namespace App\Domain\Network\Providers\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class ProviderPayableEvent extends Model
{
    public $timestamps = false;

    protected $table = 'network_provider_payable_events';

    protected $guarded = ['*'];

    protected $casts = ['occurred_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];

    protected static function booted(): void
    {
        self::creating(fn (self $e) => $e->uuid ??= (string) Str::uuid());
    }

    public function newEloquentBuilder($query)
    {
        return new ImmutableProviderBuilder($query);
    }

    protected function performUpdate(Builder $query)
    {
        throw new \LogicException('Financial audit is append-only.');
    }

    protected function performDeleteOnModel()
    {
        throw new \LogicException('Financial audit is append-only.');
    }
}
