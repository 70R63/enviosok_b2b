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
        'assigned_to', 'assigned_at', 'customer_message', 'public_response',
        'internal_notes', 'resolved_at', 'closed_at',
    ];

    protected $casts=['assigned_at'=>'datetime','respondida_at'=>'datetime','resolved_at'=>'datetime','closed_at'=>'datetime'];

    public function cotizacion()
    {
        return $this->belongsTo(B2cCotizacion::class, 'cotizacion_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignee(){return $this->belongsTo(User::class,'assigned_to');}
    public function events(){return $this->hasMany(B2cIncidenciaEvent::class,'incidencia_id')->oldest();}
}
