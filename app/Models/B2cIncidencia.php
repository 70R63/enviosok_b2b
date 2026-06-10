<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class B2cIncidencia extends Model
{
    protected $table = 'b2c_incidencias';

    protected $fillable = [
        'folio',
        'user_id',
        'cotizacion_id',
        'tracking_number',
        'tipo',
        'asunto',
        'descripcion',
        'evidencia',
        'estatus',
        'prioridad',
        'respuesta_admin',
        'respondida_at',
        'respondida_por',
    ];

    public function cotizacion()
    {
        return $this->belongsTo(B2cCotizacion::class, 'cotizacion_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
