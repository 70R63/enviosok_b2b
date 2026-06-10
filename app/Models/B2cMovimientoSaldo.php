<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class B2cMovimientoSaldo extends Model
{
    protected $table = 'b2c_movimientos_saldo';

    protected $fillable = [
        'user_id',
        'tipo',
        'monto',
        'saldo_anterior',
        'saldo_nuevo',
        'referencia',
        'estatus'
    ];
}