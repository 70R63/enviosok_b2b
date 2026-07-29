<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ZigoContractRate extends Model
{
    protected $fillable = [
        'name',
        'carrier',
        'service_code',
        'service_name',
        'included_weight_kg',
        'base_price',
        'additional_weight_unit_kg',
        'additional_weight_price',
        'tax_percentage',
        'currency',
        'source',
        'valid_from',
        'valid_to',
        'active',
        'notes',
    ];

    protected $casts = [
        'included_weight_kg' => 'decimal:3',
        'base_price' => 'decimal:2',
        'additional_weight_unit_kg' => 'decimal:3',
        'additional_weight_price' => 'decimal:2',
        'tax_percentage' => 'decimal:2',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'active' => 'boolean',
    ];

    public function scopeAvailable(
        Builder $query,
        $date = null
    ): Builder {
        $date = $date ?: today();

        return $query
            ->where('active', true)
            ->where(
                function (Builder $query) use ($date) {
                    $query
                        ->whereNull('valid_from')
                        ->orWhereDate(
                            'valid_from',
                            '<=',
                            $date
                        );
                }
            )
            ->where(
                function (Builder $query) use ($date) {
                    $query
                        ->whereNull('valid_to')
                        ->orWhereDate(
                            'valid_to',
                            '>=',
                            $date
                        );
                }
            );
    }
}
