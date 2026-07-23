<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class B2cInvoiceRequest extends Model
{
    use HasFactory;

    public const STATUS_SOLICITADA = 'SOLICITADA';
    public const STATUS_EN_PROCESO = 'EN_PROCESO';
    public const STATUS_FACTURADA = 'FACTURADA';
    public const STATUS_RECHAZADA = 'RECHAZADA';
    public const STATUS_CANCELADA = 'CANCELADA';

    protected $fillable = [
        'user_id',
        'cotizacion_id',
        'fiscal_profile_id',
        'managed_by_user_id',
        'payment_reference',
        'payment_status',
        'payment_method',
        'metodo_pago',
        'forma_pago',
        'monto',
        'razon_social',
        'rfc',
        'codigo_postal_fiscal',
        'direccion_fiscal',
        'regimen_fiscal',
        'uso_cfdi',
        'email_facturacion',
        'status',
        'solicitada_at',
        'facturada_at',
        'attended_at',
        'rejected_at',
        'cancelled_at',
        'cfdi_uuid',
        'cfdi_rfc_emisor',
        'cfdi_rfc_receptor',
        'cfdi_nombre_receptor',
        'cfdi_total',
        'cfdi_fecha_emision',
        'cfdi_fecha_timbrado',
        'pdf_path',
        'xml_path',
        'error_message',
        'internal_notes',
        'rejection_reason',
        'cancellation_reason',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'solicitada_at' => 'datetime',
        'facturada_at' => 'datetime',
        'attended_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'cfdi_total' => 'decimal:2',
        'cfdi_fecha_emision' => 'datetime',
        'cfdi_fecha_timbrado' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class
        );
    }

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(
            B2cCotizacion::class,
            'cotizacion_id'
        );
    }

    public function fiscalProfile(): BelongsTo
    {
        return $this->belongsTo(
            B2cFiscalProfile::class,
            'fiscal_profile_id'
        );
    }

    public function managedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'managed_by_user_id'
        );
    }

    public function puedeIniciarAtencion(): bool
    {
        return $this->status === self::STATUS_SOLICITADA;
    }

    public function puedeCargarDocumentos(): bool
    {
        return $this->status === self::STATUS_EN_PROCESO;
    }

    public function puedeRechazarse(): bool
    {
        return in_array(
            $this->status,
            [
                self::STATUS_SOLICITADA,
                self::STATUS_EN_PROCESO,
            ],
            true
        );
    }

    public function puedeCancelarse(): bool
    {
        return in_array(
            $this->status,
            [
                self::STATUS_SOLICITADA,
                self::STATUS_EN_PROCESO,
            ],
            true
        );
    }

    public function puedeDescargarDocumentos(): bool
    {
        return $this->status === self::STATUS_FACTURADA
            && filled($this->pdf_path)
            && filled($this->xml_path);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_SOLICITADA => 'Solicitada',
            self::STATUS_EN_PROCESO => 'En proceso',
            self::STATUS_FACTURADA => 'Facturada',
            self::STATUS_RECHAZADA => 'Rechazada',
            self::STATUS_CANCELADA => 'Cancelada',
            default => $this->status ?: 'Sin estado',
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_SOLICITADA => 'badge-yellow',
            self::STATUS_EN_PROCESO => 'badge-blue',
            self::STATUS_FACTURADA => 'badge-green',
            self::STATUS_RECHAZADA => 'badge-red',
            self::STATUS_CANCELADA => 'badge-gray',
            default => 'badge-gray',
        };
    }
}
