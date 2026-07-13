<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZigoPricingAdjustment extends Model
{
    protected $fillable = [
        'name',
        'carrier',
        'customer_segment',
        'package_type',
        'adjustment_type',
        'adjustment_value',
        'max_uses',
        'used_count',
        'starts_at',
        'ends_at',
        'active',
    ];

    protected $casts = [
        'adjustment_value' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'active' => 'boolean',
    ];

    public function isAvailable(): bool
    {
        if (!$this->active) {
            return false;
        }

        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) {
            return false;
        }

        if ($this->starts_at && now()->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at && now()->gt($this->ends_at)) {
            return false;
        }

        return true;
    }
}