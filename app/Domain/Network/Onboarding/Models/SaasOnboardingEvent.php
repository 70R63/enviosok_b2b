<?php

namespace App\Domain\Network\Onboarding\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class SaasOnboardingEvent extends Model
{
    public $timestamps = false;

    protected $table = 'saas_onboarding_events';

    protected $fillable = [
        'onboarding_application_id', 'from_status', 'to_status', 'event',
        'actor_type', 'actor_id', 'correlation_key', 'payment_event_id',
        'metadata_json', 'created_at',
    ];

    protected $casts = [
        'metadata_json' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Onboarding events are append-only.'));
        self::deleting(fn () => throw new LogicException('Onboarding events are append-only.'));
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(SaasOnboardingApplication::class, 'onboarding_application_id');
    }
}
