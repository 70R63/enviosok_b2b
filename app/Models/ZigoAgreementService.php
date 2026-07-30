<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ZigoAgreementService extends Model
{
    protected $fillable = [
        'shipping_agreement_id',
        'servicio_id',
        'external_service_code',
        'display_name',
        'priority',
        'active',
    ];

    protected $casts = [
        'priority' => 'integer',
        'active' => 'boolean',
    ];

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(
            ZigoShippingAgreement::class,
            'shipping_agreement_id'
        );
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(
            Servicio::class,
            'servicio_id'
        );
    }

    public function rateCards(): HasMany
    {
        return $this->hasMany(
            ZigoProviderRateCard::class,
            'agreement_service_id'
        );
    }


    public function rateReferences(): HasMany
    {
        return $this->hasMany(
            ZigoProviderRateReference::class,
            'agreement_service_id'
        );
    }

    public function activeRateReference(): HasOne
    {
        return $this->hasOne(
            ZigoProviderRateReference::class,
            'agreement_service_id'
        )
            ->where(
                'status',
                ZigoProviderRateReference::STATUS_ACTIVE
            )
            ->latestOfMany('version');
    }

    public function quoteObservations(): HasMany
    {
        return $this->hasMany(
            ZigoProviderQuoteObservation::class,
            'agreement_service_id'
        );
    }

    public function latestQuoteObservation(): HasOne
    {
        return $this->hasOne(
            ZigoProviderQuoteObservation::class,
            'agreement_service_id'
        )->latestOfMany();
    }
}
