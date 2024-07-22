<?php

namespace App\Models\Guias;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Log;

class Masivas extends Model
{
    use HasFactory;


    protected $fillable = ['user_id','no_registros', 'archivo_nombre', 'archivo_fallo', 'no_registros_fallo', 'ruta_zip'];

    public function scopeJoinUsuario($query) {
      Log::info(__CLASS__." ".__FUNCTION__);
      return $query->join('users', 'users.id', '=', 'user_id');
    }

}
