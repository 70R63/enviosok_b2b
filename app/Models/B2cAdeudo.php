<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class B2cAdeudo extends Model
{
    public const STATUS_PENDING = 'PENDIENTE';
    public const STATUS_PAYMENT_STARTED = 'PAGO_INICIADO';
    public const STATUS_PAID_BALANCE = 'PAGADO_SALDO';
    public const STATUS_PAID_MERCADOPAGO = 'PAGADO_MERCADOPAGO';
    public const STATUS_CANCELLED = 'CANCELADO';
    public const STATUS_WAIVED = 'CONDONADO';

    protected $table = 'b2c_adeudos';

    protected $fillable = [
        'user_id',
        'cotizacion_id',
        'guia_id',
        'tracking_number',
        'tipo',
        'concepto',
        'peso_cotizado',
        'peso_real',
        'costo_cotizado',
        'costo_real',
        'monto',
        'referencia_xperta',
        'observaciones',
        'evidencia_path',
        'estatus',
        'payment_method',
        'payment_id',
        'payment_external_reference',
        'created_by',
        'cancelled_by',
        'cancel_reason',
        'paid_at',
        'cancelled_at',
    ];

    protected $casts = [
        'peso_cotizado' => 'decimal:2',
        'peso_real' => 'decimal:2',
        'costo_cotizado' => 'decimal:2',
        'costo_real' => 'decimal:2',
        'monto' => 'decimal:2',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(
            B2cCotizacion::class,
            'cotizacion_id'
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isPending(): bool
    {
        return in_array($this->estatus, [
            self::STATUS_PENDING,
            self::STATUS_PAYMENT_STARTED,
        ], true);
    }

    public function isPaid(): bool
    {
        return in_array($this->estatus, [
            self::STATUS_PAID_BALANCE,
            self::STATUS_PAID_MERCADOPAGO,
        ], true);
    }

    public function getEstatusLabelAttribute(): string
    {
        return match ($this->estatus) {
            self::STATUS_PENDING => 'Pendiente',
            self::STATUS_PAYMENT_STARTED => 'Pago iniciado',
            self::STATUS_PAID_BALANCE => 'Pagado con saldo',
            self::STATUS_PAID_MERCADOPAGO => 'Pagado con Mercado Pago',
            self::STATUS_CANCELLED => 'Cancelado',
            self::STATUS_WAIVED => 'Condonado',
            default => ucfirst(
                strtolower(
                    str_replace('_', ' ', (string) $this->estatus)
                )
            ),
        };
    }
}
