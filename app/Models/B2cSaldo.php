<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class B2cSaldo extends Model
{
    protected $table = 'b2c_saldos';

    protected $fillable = [
        'user_id',
        'saldo'
    ];
}