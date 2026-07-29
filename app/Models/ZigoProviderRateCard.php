<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ZigoProviderRateCard extends Model
{
    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_INACTIVE = 'INACTIVE';
    public const STATUS_ARCHIVED = 'ARCHIVED';

    public const SCHEME_DYNAMIC_API = 'DYNAMIC_API';
    public const SCHEME_FLAT = 'FLAT';
    public const SCHEME_BASE_PLUS_EXTRA = 'BASE_PLUS_EXTRA';
    public const SCHEME_WEIGHT_RANGE = 'WEIGHT_RANGE';
    public const SCHEME_ZONE_RANGE = 'ZONE_RANGE';

    protected $fillable = [
        'agreement_service_id',
        'name',
        'pricing_scheme',
        'version',
        'status',
        'currency',
        'tax_percentage',
        'priority',
        'valid_from',
        'valid_to',
        'source_reference',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'version' => 'integer',
        'tax_percentage' => 'decimal:2',
        'priority' => 'integer',
        'valid_from' => 'date',
        'valid_to' => 'date',
    ];

    public function agreementService(): BelongsTo
    {
        return $this->belongsTo(
            ZigoAgreementService::class,
            'agreement_service_id'
        );
    }

    public function lines(): HasMany
    {
        return $this->hasMany(
            ZigoProviderRateLine::class,
            'rate_card_id'
        )->orderBy('sort_order');
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
