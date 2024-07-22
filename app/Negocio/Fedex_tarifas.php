<?php

namespace App\Negocio;

use Log;
use App\Models\Tarifa;

use App\Models\PostalZona;
use App\Models\PostalGrupo;
use App\Models\LtdCobertura;

class Fedex_tarifas 
{
    private $tarifa = array();
    private $zona = "";
    
    /**
     * Funcion para poder obtener la cotizacion ligado a la zona de FEDEX
     * 
     * @param \Illuminate\Http\Request $request
     * @return array body
     */

    public function zona($request, $servicioIds,$empresa_id,$ltdId){
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);

        $zona = Tarifa::fedexZona($request['cp'],$request['cp_d']);

        foreach ($servicioIds as $key => $value) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." servicio_id =$value");
            $query = Tarifa::base($empresa_id, $request['cp_d'], $ltdId);
            $query->where('servicio_id', $value)
                        ->where("zona",$zona)
                        ->where( 'kg_ini', "<=", $request['pesoFacturado'] )
                        ->where('kg_fin', ">=", $request['pesoFacturado'] )
                    ;
            $tablaTmp = $query->get()->toArray();
            $this->tarifa = array_merge($this->tarifa, $tablaTmp);
            //Log::debug(print_r($query->get()->toArray(),true));
            //Log::debug(print_r($query->toSql(),true));
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            Log::debug(print_r($zona,true));
        }
       
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);

    }

    /**
     * Valida la existencia de las tarifas y aplica el calculo basico descuenteo, FSC etc
     * 
     * @author Javier Hernandez
     * @copyright 2022-2023 XpertaMexico
     * @package App\Negocio
     * @api
     * 
     * @version 1.0.0
     * 
     * @since 1.0.0 Primera version de la funcion fedexApi
     * 
     * @throws
     *
     * @param array $data Informacion general de la petricion
     * 
     * @var int 
     * 
     * 
     * @return void los valores sencibles se obtine de getters
     */

    public function mostrador($data){


    }


    /**
     * Valida la existencia de las tarifas y aplica el calculo basico descuenteo, FSC etc
     * 
     * @author Javier Hernandez
     * @copyright 2022-2023 XpertaMexico
     * @package App\Negocio
     * @api
     * 
     * @version 1.0.0
     * 
     * @since 1.0.0 Primera version de la funcion fedexApi
     * 
     * @throws
     *
     * @param array $data Informacion general de la petricion
     * 
     * @var int 
     * 
     * 
     * @return void los valores sencibles se obtine de getters
     */

    public function zonas($cp, $cp_d){

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $postalGrupoOrigen = PostalGrupo::where("cp_inicial", "<=", $cp)
            ->where("cp_final", ">=", $cp)
            ->pluck("grupo")->toArray();
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $postalGrupoDestino = PostalGrupo::where("cp_inicial", "<=", $cp_d)
            ->where("cp_final", ">=", $cp_d)
            ->pluck("grupo")->toArray();

        Log::debug($postalGrupoOrigen);
        Log::debug($postalGrupoDestino);
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);


        $this->zona = PostalZona::where("grupo_origen", $postalGrupoOrigen)
            ->where("grupo_destino", $postalGrupoDestino)
            ->pluck("zona")->toArray();

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        //return $zona[0]; 

    }


    


    public function getZona(){
        return $this->zona;
    }

    public function getTarifa(){
        return $this->tarifa;
    }
}
