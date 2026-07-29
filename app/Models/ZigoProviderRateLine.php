<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZigoProviderRateLine extends Model
{
    protected $fillable = [
        'rate_card_id',
        'zone_code',
        'origin_zone',
        'destination_zone',
        'min_weight_kg',
        'max_weight_kg',
        'included_weight_kg',
        'base_price',
        'additional_weight_unit_kg',
        'additional_weight_price',
        'extended_area_price',
        'oversize_price',
        'insurance_percentage',
        'fuel_surcharge_percentage',
        'multipiece_price',
        'sort_order',
        'active',
    ];

    protected $casts = [
        'min_weight_kg' => 'decimal:3',
        'max_weight_kg' => 'decimal:3',
        'included_weight_kg' => 'decimal:3',
        'base_price' => 'decimal:2',
        'additional_weight_unit_kg' => 'decimal:3',
        'additional_weight_price' => 'decimal:2',
        'extended_area_price' => 'decimal:2',
        'oversize_price' => 'decimal:2',
        'insurance_percentage' => 'decimal:4',
        'fuel_surcharge_percentage' => 'decimal:4',
        'multipiece_price' => 'decimal:2',
        'sort_order' => 'integer',
        'active' => 'boolean',
    ];

    public function rateCard(): BelongsTo
    {
        return $this->belongsTo(
            ZigoProviderRateCard::class,
            'rate_card_id'
        );
    }
}
