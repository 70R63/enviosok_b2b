<?php

namespace App\Models\API;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Log;
use Carbon\Carbon;
use DateTimeInterface;

use App\Models\API\Sucursal;
use App\Models\EmpresaEmpresas;

class Reportes_ventas extends Model
{
    use HasFactory;

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format("Y-m-d H:i:s");
    }


    public function scopeFiltro($query, $parametros) {
        
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
      

        if ( !empty($parametros['ltdId']) ) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            $query->where('ltd_id', $parametros['ltdId']);
        }

        if ( !empty($parametros['clienteIdCombo']) ) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            $empresaId = $parametros['clienteIdCombo'];
            $empresas = Sucursal::where('empresa_id',$empresaId)->pluck('id')->toArray();    
            $query->whereIN('cia', $empresas);
        } else {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            $empresaId = auth()->user()->empresa_id;
            $empresas = EmpresaEmpresas::where('id',$empresaId)->pluck('empresa_id')->toArray();
            Log::debug(print_r($empresas,true));    
            $query->whereIN('empresa_id', $empresas);
        }
       

        if ( !empty($parametros['servicio_id']) ) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            $query->where('servicio_id', $parametros['servicio_id']);
        }
        
        
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
        return $query;
        
   }

   
}
