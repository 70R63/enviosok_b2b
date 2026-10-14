<?php

namespace App\Domain\Network\Providers\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class ProviderFulfillmentDecision extends Model
{
    protected $table = 'network_provider_fulfillment_decisions';

    protected $guarded = ['*'];

    protected $hidden = ['id', 'provider_fulfillment_id', 'actor_user_id', 'rejection_reason', 'channel'];

    protected $casts = ['decided_at' => 'immutable_datetime'];

    protected static function booted(): void
    {
        self::creating(function (self $decision): void {
            if (! in_array($decision->status, ['ACCEPTED', 'REJECTED'], true)
                || ! in_array($decision->channel, ['TENANT_UI', 'INTERNAL'], true)
                || ! $decision->actor_user_id || ! $decision->decided_at
                || ($decision->status === 'ACCEPTED' && $decision->rejection_reason !== null)
                || ($decision->rejection_reason !== null && (mb_strlen($decision->rejection_reason) > 500
                    || preg_match('/[\x00-\x1F\x7F]/u', $decision->rejection_reason)))) {
                throw new \DomainException('INVALID_PROVIDER_DECISION');
            }
            $decision->uuid ??= (string) Str::uuid();
        });
    }

    public function newEloquentBuilder($query)
    {
        return new ImmutableProviderBuilder($query);
    }

    protected function performUpdate(Builder $query)
    {
        throw new \LogicException('Provider decision is immutable.');
    }

    protected function performDeleteOnModel()
    {
        throw new \LogicException('Provider decision is immutable.');
    }
}
