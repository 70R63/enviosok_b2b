<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ZigoShippingAgreement extends Model
{
    public const MODE_DYNAMIC_API = 'DYNAMIC_API';
    public const MODE_MANUAL = 'MANUAL';
    public const MODE_HYBRID = 'HYBRID';

    protected $fillable = [
        'provider_source_id',
        'ltd_id',
        'name',
        'rate_mode',
        'currency',
        'priority',
        'active',
        'valid_from',
        'valid_to',
        'external_reference',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'priority' => 'integer',
        'active' => 'boolean',
        'valid_from' => 'date',
        'valid_to' => 'date',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(
            ZigoProviderSource::class,
            'provider_source_id'
        );
    }

    public function ltd(): BelongsTo
    {
        return $this->belongsTo(
            Ltd::class,
            'ltd_id'
        );
    }

    public function services(): HasMany
    {
        return $this->hasMany(
            ZigoAgreementService::class,
            'shipping_agreement_id'
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'updated_by'
        );
    }
}
