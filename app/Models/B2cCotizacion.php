<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class B2cCotizacion extends Model
{
    use HasFactory;

    protected $table = 'b2c_cotizaciones';

    protected $fillable = [
        'user_id',

        'cp_origen',
        'colonia_origen',
        'ciudad_origen',
        'estado_origen',

        'cp_destino',
        'colonia_destino',
        'ciudad_destino',
        'estado_destino',

        'tipo_envio',
        'peso',
        'peso_real',
        'peso_volumetrico',
        'peso_facturable',
        'medidas',

        'logistico',
        'servicio',
        'precio',
        'estatus',

        'remitente_nombre',
        'remitente_telefono',
        'remitente_email',
        'remitente_direccion',
        'remitente_num_ext',
        'remitente_num_int',

        'destinatario_nombre',
        'destinatario_telefono',
        'destinatario_email',
        'destinatario_direccion',
        'destinatario_num_ext',
        'destinatario_num_int',

        'contenido',
        'valor_declarado',

        'requiere_seguro_envio',
        'seguro_porcentaje',
        'seguro_iva_porcentaje',
        'seguro_monto',
        'precio_sin_seguro',

        'referencia',

        'guia_id',
        'tracking_number',
        'documento',
        'guia_estatus',
        'guia_provider_reference',
        'guia_provider_request_number',
        'guia_generation_attempts',
        'guia_generation_started_at',
        'guia_last_attempt_at',
        'guia_generated_at',
        'guia_recovered_at',
        'guia_last_error_code',
        'guia_last_error_message',
        'guia_request_snapshot',
        'guia_response_snapshot',

        'payment_id',
        'payment_status',
        'payment_external_reference',
        'payment_collection_id',
        'payment_verification_status',
        'payment_verification_source',
        'payment_verification_error',
        'payment_verification_attempted_at',
        'payment_verified_at',
        'payment_verified_amount',
        'payment_verified_currency',
        'payment_verified_external_reference',
        'payment_verification_payload',

        'provider_base_price',
        'zigo_margin_percentage',
        'zigo_fixed_fee',
        'zigo_margin_amount',
        'zigo_adjustment_type',
        'zigo_adjustment_value',
        'zigo_adjustment_amount',
        'zigo_discount_type',
        'zigo_discount_value',
        'zigo_discount_amount',
        'zigo_final_price',
        'zigo_profit_amount',
        'zigo_customer_segment',
        'zigo_pricing_rule_id',
        'zigo_pricing_adjustment_id',
        'zigo_client_pricing_rule_id',
    ];

    protected $casts = [
        'peso' => 'decimal:2',
        'peso_real' => 'decimal:2',
        'peso_volumetrico' => 'decimal:2',
        'peso_facturable' => 'decimal:2',

        'precio' => 'decimal:2',
        'valor_declarado' => 'decimal:2',

        'requiere_seguro_envio' => 'boolean',
        'seguro_porcentaje' => 'decimal:2',
        'seguro_iva_porcentaje' => 'decimal:2',
        'seguro_monto' => 'decimal:2',
        'precio_sin_seguro' => 'decimal:2',

        'provider_base_price' => 'decimal:2',
        'zigo_margin_percentage' => 'decimal:2',
        'zigo_fixed_fee' => 'decimal:2',
        'zigo_margin_amount' => 'decimal:2',
        'zigo_adjustment_value' => 'decimal:2',
        'zigo_adjustment_amount' => 'decimal:2',
        'zigo_discount_value' => 'decimal:2',
        'zigo_discount_amount' => 'decimal:2',
        'zigo_final_price' => 'decimal:2',
        'zigo_profit_amount' => 'decimal:2',

        'payment_verification_attempted_at' => 'datetime',
        'payment_verified_at' => 'datetime',
        'payment_verified_amount' => 'decimal:2',
        'payment_verification_payload' => 'array',

        'guia_generation_attempts' => 'integer',
        'guia_generation_started_at' => 'datetime',
        'guia_last_attempt_at' => 'datetime',
        'guia_generated_at' => 'datetime',
        'guia_recovered_at' => 'datetime',
        'guia_request_snapshot' => 'array',
        'guia_response_snapshot' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(
            \App\Models\User::class,
            'user_id'
        );
    }

    public function invoiceRequest(): HasOne
    {
        return $this->hasOne(
            B2cInvoiceRequest::class,
            'cotizacion_id'
        );
    }

    public function getEstatusLabelAttribute(): string
    {
        $estatus = strtoupper(
            trim((string) $this->estatus)
        );

        return match ($estatus) {
            'DIRECCION_CAPTURADA' =>
                'Direcciones capturadas',

            'PAQUETE_CAPTURADO' =>
                'Paquete capturado',

            'COTIZADA' =>
                'Cotización creada',

            'SELECCIONADA' =>
                'Pendiente de pago',

            'CHECKOUT_COMPLETO' =>
                'Lista para pagar',

            'PAGO_INICIADO' =>
                'Pago iniciado',

            'PAGO_PENDIENTE' =>
                'Pago pendiente',

            'PAGO_EN_VERIFICACION' =>
                'Pago en verificación',

            'PAGO_VERIFICACION_FALLIDA' =>
                'Pago con validación pendiente',

            'PAGO_RECHAZADO' =>
                'Pago rechazado',

            'PAGADA' =>
                'Pagada',

            'GUIA_GENERADA' =>
                'Guía generada',

            'ERROR_GENERACION_GUIA' =>
                'La guía requiere atención',

            default =>
                $estatus !== ''
                    ? ucfirst(
                        strtolower(
                            str_replace(
                                '_',
                                ' ',
                                $estatus
                            )
                        )
                    )
                    : 'Sin estado',
        };
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        $paymentStatus = strtolower(
            trim((string) $this->payment_status)
        );

        if ($paymentStatus === '') {
            $estatus = strtoupper(
                trim((string) $this->estatus)
            );

            return match ($estatus) {
                'PAGADA',
                'GUIA_GENERADA',
                'ERROR_GENERACION_GUIA' =>
                    'Pagado',

                'PAGO_INICIADO',
                'PAGO_PENDIENTE' =>
                    'Pago pendiente',

                'PAGO_RECHAZADO' =>
                    'Pago rechazado',

                default =>
                    'Sin pago',
            };
        }

        return match ($paymentStatus) {
            'saldo_prepago' =>
                'Pagado con saldo',

            'approved' =>
                'Pago aprobado',

            'pending',
            'in_process' =>
                'Pago pendiente',

            'rejected' =>
                'Pago rechazado',

            'cancelled',
            'cancelled_by_user' =>
                'Pago cancelado',

            default =>
                ucfirst(
                    str_replace(
                        '_',
                        ' ',
                        $paymentStatus
                    )
                ),
        };
    }

    public function getGuiaEstatusLabelAttribute(): string
    {
        $estatus = strtoupper(
            trim((string) $this->guia_estatus)
        );

        return match ($estatus) {
            'GENERADA' =>
                'Guía generada',

            'GENERANDO' =>
                'Generando guía',

            'GENERADA_SIN_DOCUMENTO' =>
                'Guía generada; documento en revisión',

            'ERROR_PROVEEDOR',
            'ERROR_GENERACION_GUIA' =>
                'La guía requiere atención',

            'ERROR_VALIDACION_PESO' =>
                'Peso fuera del límite',

            'SIN_GUIA',
            '' =>
                'Sin guía',

            default =>
                ucfirst(
                    strtolower(
                        str_replace(
                            '_',
                            ' ',
                            $estatus
                        )
                    )
                ),
        };
    }
}