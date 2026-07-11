<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZigoPostalCode extends Model
{
    protected $table = 'zigo_postal_codes';

    protected $fillable = [
        'codigo_postal',
        'estado',
        'municipio',
        'ciudad',
        'asentamiento',
        'tipo_asentamiento',
        'zona',
        'cobertura_estafeta',
        'activo',
    ];

    protected $casts = [
        'cobertura_estafeta' => 'boolean',
        'activo' => 'boolean',
    ];
}
