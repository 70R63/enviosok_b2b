<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
