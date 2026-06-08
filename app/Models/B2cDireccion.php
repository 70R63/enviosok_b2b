<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class B2cDireccion extends Model
{
    use HasFactory;

    protected $table = 'b2c_direcciones';

    protected $fillable = [
        'user_id',
        'tipo',
        'alias',
        'nombre',
        'empresa',
        'email',
        'telefono',
        'calle',
        'num_ext',
        'num_int',
        'referencias',
        'cp',
        'colonia',
        'ciudad',
        'estado',
        'principal',
        'activo',
        'alias',
        'favorita',
    ];
}
