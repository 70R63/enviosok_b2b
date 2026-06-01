<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class B2cCotizacion extends Model
{
    use HasFactory;

    protected $table = 'b2c_cotizaciones';

    protected $fillable = [
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
    ];
}