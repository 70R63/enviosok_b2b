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
        
    ];

public function user()
{
    return $this->belongsTo(\App\Models\User::class, 'user_id');
}

}