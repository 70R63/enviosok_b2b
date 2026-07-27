<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class B2cSaldoReversal extends Model
{
    protected $table = 'b2c_saldo_reversals';

    protected $fillable = [
        'cotizacion_id',
        'user_id',
        'purchase_movement_id',
        'reversal_movement_id',
        'admin_user_id',
        'amount',
        'reason',
        'provider_confirmation',
        'status',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'provider_confirmation' => 'boolean',
        'metadata' => 'array',
    ];

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(
            B2cCotizacion::class,
            'cotizacion_id'
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'user_id'
        );
    }

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'admin_user_id'
        );
    }

    public function purchaseMovement(): BelongsTo
    {
        return $this->belongsTo(
            B2cMovimientoSaldo::class,
            'purchase_movement_id'
        );
    }

    public function reversalMovement(): BelongsTo
    {
        return $this->belongsTo(
            B2cMovimientoSaldo::class,
            'reversal_movement_id'
        );
    }
}
