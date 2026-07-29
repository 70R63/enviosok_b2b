<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ZigoProviderSource extends Model
{
    public const TYPE_INTEGRATOR = 'INTEGRATOR';
    public const TYPE_DIRECT = 'DIRECT';
    public const TYPE_MANUAL = 'MANUAL';

    protected $fillable = [
        'code',
        'name',
        'source_type',
        'active',
        'notes',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function agreements(): HasMany
    {
        return $this->hasMany(
            ZigoShippingAgreement::class,
            'provider_source_id'
        );
    }
}
