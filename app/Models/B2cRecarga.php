<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class B2cRecarga extends Model
{
    protected $table = 'b2c_recargas';

    protected $fillable = [
        'user_id',
        'monto',
        'estatus',
        'mp_preference_id',
        'mp_payment_id',
        'mp_status',
        'referencia',
    ];
}