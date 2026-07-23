<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class B2cInvoiceRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'cotizacion_id',
        'fiscal_profile_id',
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
        'cfdi_uuid',
        'pdf_path',
        'xml_path',
        'error_message',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'solicitada_at' => 'datetime',
        'facturada_at' => 'datetime',
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
}