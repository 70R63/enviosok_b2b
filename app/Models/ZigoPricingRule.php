<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZigoPricingRule extends Model
{
    protected $fillable = [
        'name',
        'carrier',
        'customer_segment',
        'plan',
        'package_type',
        'margin_percentage',
        'fixed_fee',
        'min_price',
        'active',
    ];

    protected $casts = [
        'margin_percentage' => 'decimal:2',
        'fixed_fee' => 'decimal:2',
        'min_price' => 'decimal:2',
        'active' => 'boolean',
    ];
}