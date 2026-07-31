<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class B2cCheckoutDebtAllocation extends Model
{
    public const STATUS_RESERVED = 'RESERVADO';
    public const STATUS_APPLIED = 'APLICADO';
    public const STATUS_RELEASED = 'LIBERADO';

    protected $table = 'b2c_checkout_debt_allocations';

    protected $fillable = [
        'checkout_cotizacion_id',
        'adeudo_id',
        'user_id',
        'monto',
        'estatus',
        'payment_method',
        'payment_reference',
        'reserved_at',
        'paid_at',
        'released_at',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'reserved_at' => 'datetime',
        'paid_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public function checkoutCotizacion(): BelongsTo
    {
        return $this->belongsTo(
            B2cCotizacion::class,
            'checkout_cotizacion_id'
        );
    }

    public function adeudo(): BelongsTo
    {
        return $this->belongsTo(B2cAdeudo::class, 'adeudo_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
