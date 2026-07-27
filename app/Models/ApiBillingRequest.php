<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiBillingRequest extends Model
{
    public const STATUS_SOLICITADA = 'SOLICITADA';
    public const STATUS_EN_PROCESO = 'EN_PROCESO';
    public const STATUS_FACTURADA = 'FACTURADA';
    public const STATUS_RECHAZADA = 'RECHAZADA';
    public const STATUS_CANCELADA = 'CANCELADA';

    protected $fillable = [
        'api_client_id',
        'api_key_id',
        'managed_by_user_id',
        'environment',
        'external_id',
        'idempotency_key',
        'payload_hash',
        'source_system',
        'status',
        'fulfillment_mode',
        'provider_code',
        'provider_reference',
        'payment_reference',
        'payment_status',
        'payment_method',
        'payment_form',
        'payment_date',
        'currency',
        'exchange_rate',
        'subtotal',
        'discount_total',
        'tax_total',
        'shipping_total',
        'insurance_total',
        'total',
        'customer_rfc',
        'customer_name',
        'customer_postal_code',
        'customer_tax_regime',
        'customer_cfdi_use',
        'customer_email',
        'request_payload',
        'response_payload',
        'internal_notes',
        'rejection_reason',
        'cancellation_reason',
        'cfdi_uuid',
        'cfdi_rfc_emisor',
        'cfdi_rfc_receptor',
        'cfdi_nombre_receptor',
        'cfdi_total',
        'cfdi_fecha_emision',
        'cfdi_fecha_timbrado',
        'pdf_path',
        'xml_path',
        'error_code',
        'error_message',
        'requested_at',
        'processing_at',
        'issued_at',
        'rejected_at',
        'cancelled_at',
    ];

    protected $casts = [
        'exchange_rate' => 'decimal:6',
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'shipping_total' => 'decimal:2',
        'insurance_total' => 'decimal:2',
        'total' => 'decimal:2',
        'request_payload' => 'array',
        'response_payload' => 'array',
        'cfdi_total' => 'decimal:2',
        'cfdi_fecha_emision' => 'datetime',
        'cfdi_fecha_timbrado' => 'datetime',
        'payment_date' => 'datetime',
        'requested_at' => 'datetime',
        'processing_at' => 'datetime',
        'issued_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(
            ApiClient::class,
            'api_client_id'
        );
    }

    public function apiKey()
    {
        return $this->belongsTo(
            ApiKey::class,
            'api_key_id'
        );
    }


    public function manager()
    {
        return $this->belongsTo(
            User::class,
            'managed_by_user_id'
        );
    }

    public function canStartProcessing(): bool
    {
        return $this->status === self::STATUS_SOLICITADA;
    }

    public function canManage(): bool
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

    public function canUploadDocuments(): bool
    {
        return $this->status === self::STATUS_EN_PROCESO;
    }

    public function documentsReady(): bool
    {
        return $this->status === self::STATUS_FACTURADA
            && trim((string) $this->pdf_path) !== ''
            && trim((string) $this->xml_path) !== '';
    }

    public function b2cInvoiceRequest()
    {
        return $this->hasOne(
            B2cInvoiceRequest::class,
            'api_billing_request_id'
        );
    }

    public function webhookDeliveries()
    {
        return $this->hasMany(
            ApiWebhookDelivery::class,
            'api_billing_request_id'
        );
    }

    public function items()
    {
        return $this->hasMany(
            ApiBillingRequestItem::class,
            'api_billing_request_id'
        )->orderBy('line_number');
    }
}
