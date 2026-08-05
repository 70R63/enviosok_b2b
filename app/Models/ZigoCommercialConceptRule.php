<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZigoCommercialConceptRule extends Model
{
    protected $fillable = [
        'name', 'carrier', 'service', 'segment', 'plan', 'package_type',
        'concept', 'adjustment_type', 'value', 'priority',
        'starts_at', 'ends_at', 'active',
    ];

    protected $casts = [
        'value' => 'decimal:4',
        'priority' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'active' => 'boolean',
    ];

    public function isAvailable(): bool
    {
        return $this->active
            && (!$this->starts_at || now()->gte($this->starts_at))
            && (!$this->ends_at || now()->lte($this->ends_at));
    }
}
