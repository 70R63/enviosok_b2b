<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZigoClientPricingRule extends Model
{
    protected $fillable = [
        'crm_client_id',
        'api_client_id',
        'user_id',
        'name',
        'customer_segment',
        'package_type',
        'discount_type',
        'discount_value',
        'max_uses',
        'used_count',
        'starts_at',
        'ends_at',
        'active',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
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