<?php

namespace App\Models\Saldos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Log;
use Carbon\Carbon;
class Pagos extends Model
{
    use HasFactory;

    protected $casts = [
        'fecha_deposito'  => 'date:Y-m-d',
    
    ];
    
    protected $fillable = ['empresa_id','tipo_pago_id','banco_id', 'referencia', 'importe', 'fecha_deposito', 'hora_deposito', "usuario_id", "created_at"];


    public function scopeJoinEmpresa($query) {
      Log::info(__CLASS__." ".__FUNCTION__);
      return $query->join('empresas', 'empresas.id', '=', 'empresa_id');
    }
    
    public function scopeJoinBancos($query) {
      Log::info(__CLASS__." ".__FUNCTION__);
      return $query->join('bancos', 'bancos.id', '=', 'banco_id');
    }

    public function scopeJoinUsuario($query) {
      Log::info(__CLASS__." ".__FUNCTION__);
      return $query->join('users', 'users.id', '=', 'usuario_id');
    }

    public function scopeFiltro($query, $parametros) {
      Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
      if ( !empty($parametros['fecha_ini']) ) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            $carbon = Carbon::parse($parametros['fecha_ini'])->format('Y-m-d');
            
            Log::debug($carbon);
            $query->where('pagos.created_at','>=', $carbon." 00:00:00");
        }

        
        if ( !empty($parametros['fecha_fin']) ) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            $carbon = Carbon::parse($parametros['fecha_fin'])->format('Y-m-d');
            Log::debug($carbon);
            $query->where('pagos.created_at','<=', $carbon." 23:59:59");
        }

        if ( !empty($parametros['banco_id']) &&  $parametros['banco_id'] > 0) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            $query->where('banco_id','=', $parametros['banco_id'] );
        }

        if ( !empty($parametros['empresa_id']) &&  $parametros['empresa_id'] > 0) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            $query->where('pagos.empresa_id','=', $parametros['empresa_id'] );
        }
    }
}
