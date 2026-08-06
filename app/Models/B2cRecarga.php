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

    protected $casts = ['monto' => 'decimal:2'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function movimiento()
    {
        return $this->hasOne(B2cMovimientoSaldo::class, 'user_id', 'user_id')
            ->where('tipo', 'RECARGA')
            ->whereColumn('b2c_movimientos_saldo.referencia', '=',
                \DB::raw("CONCAT('RECARGA-', b2c_recargas.id)"));
    }
}
