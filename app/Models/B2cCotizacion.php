<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class B2cCotizacion extends Model
{
    use HasFactory;

    protected $table = 'b2c_cotizaciones';

    protected $fillable = [
        'user_id',
        'cp_origen',
        'colonia_origen',
        'cp_destino',
        'colonia_destino',
        'tipo_envio',
        'peso',
        'medidas',
        'logistico',
        'servicio',
        'precio',
        'estatus',
		'remitente_nombre',
        'remitente_telefono',
        'remitente_email',
        'remitente_direccion',
        'destinatario_nombre',
        'destinatario_telefono',
        'destinatario_email',
        'destinatario_direccion',
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
        'payment_id',
        'payment_status',
        'payment_external_reference',
        'payment_collection_id',
        'remitente_num_ext',
        'remitente_num_int',
        'ciudad_origen',
        'estado_origen',
        'destinatario_num_ext',
        'destinatario_num_int',
        'ciudad_destino',
        'estado_destino',
        'provider_base_price',
        'precio' => 'decimal:2',
        'valor_declarado' => 'decimal:2',
        'requiere_seguro_envio' => 'boolean',
        'seguro_porcentaje' => 'decimal:2',
        'seguro_iva_porcentaje' => 'decimal:2',
        'seguro_monto' => 'decimal:2',
        'precio_sin_seguro' => 'decimal:2',
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
        
    ];

public function user()
{
    return $this->belongsTo(\App\Models\User::class, 'user_id');
}

}