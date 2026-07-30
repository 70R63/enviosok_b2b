<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZigoProviderQuoteObservation extends Model
{
    protected $fillable = [
        'agreement_service_id',
        'b2c_cotizacion_id',
        'provider_source_code',
        'service_code',
        'origin_zip',
        'destination_zip',
        'weight_kg',
        'length_cm',
        'width_cm',
        'height_cm',
        'currency',
        'total',
        'extended_area_amount',
        'success',
        'error_message',
        'response_payload',
        'observed_at',
    ];

    protected $casts = [
        'weight_kg' => 'decimal:3',
        'length_cm' => 'decimal:3',
        'width_cm' => 'decimal:3',
        'height_cm' => 'decimal:3',
        'total' => 'decimal:2',
        'extended_area_amount' => 'decimal:2',
        'success' => 'boolean',
        'response_payload' => 'array',
        'observed_at' => 'datetime',
    ];

    public function agreementService(): BelongsTo
    {
        return $this->belongsTo(
            ZigoAgreementService::class,
            'agreement_service_id'
        );
    }

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(
            B2cCotizacion::class,
            'b2c_cotizacion_id'
        );
    }
}
