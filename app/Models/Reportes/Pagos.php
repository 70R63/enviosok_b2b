<?php

namespace App\Models\Reportes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Log;

class Pagos extends Model
{
    use HasFactory;
    protected $table = 'reporte_pagos';

    protected $fillable = ['user_id', 'empresa_id', 'banco_id', 'fecha_ini', 'fecha_fin', 'ruta_csv', 'registros_cantidad'];


    public function scopeFiltro($query, $parametros) {
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);

         if ( !empty($parametros['fecha_ini']) ) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            $carbon = Carbon::parse($parametros['fecha_ini'])->format('Y-m-d');
            
            Log::debug($carbon);
            $query->where('created_at','>=', $carbon." 00:00:00");
        }

        
        if ( !empty($parametros['fecha_fin']) ) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            $carbon = Carbon::parse($parametros['fecha_fin'])->format('Y-m-d');
            //$carbon->settings(['toStringFormat' => 'Y-m-d-H-i-s']);
            Log::debug($carbon);
            $query->where('created_at','<=', $carbon." 23:59:59");
        }
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
    
    }

    /**
     * 
     * 
     * @param array $parametros
     * @return void
     */

    public function scopeJoinBancos($query){
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        return $query->leftJoin('bancos', 'bancos.id', '=', 'banco_id');
    }

    /**
     * 
     * 
     * @param array $parametros
     * @return void
     */

    public function scopeJoinUsers($query){
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        return $query->join('users', 'users.id', '=', 'user_id');
    }

    /**
     * 
     * 
     * @param 
     * @return void
     */

    public function scopeJoinEmpresas($query){
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        return $query->leftJoin('empresas', 'empresas.id', '=', 'reporte_pagos.empresa_id');
    }
}
