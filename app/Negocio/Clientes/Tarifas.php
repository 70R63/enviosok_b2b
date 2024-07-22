<?php

namespace App\Negocio\Clientes;

use Log;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use DB;

//MODELS
use App\Models\Tarifa;
use App\Models\API\Tarifa as mTarifa;
use App\Models\Ltd;
use App\Models\Servicio;
use App\Models\Empresa;
use App\Models\API\Empresa as mEmpresaApi;

//DTOS

// singlenton


//Negocio


class Tarifas {

	/**
     * Se obtienen los datos de las tarifas de los clietnes ligados al cliente
     * 
     * @author Javier Hernandez
     * @copyright 2022-2024 XpertaMexico
     * @package App\Negocio\Clientes
     * @api
     * 
     * @version 1.0.0
     * 
     * @since 1.0.0 Primera version de la funcion obtener
     * 
     * @throws \LogicException
     *
     * @param array $parametros eseseses
     * 
     * @var int 
     * @var App\Negocio\Fedex_tarifas $fedexTarifa
     * @var string $cp 
     * @var string $cp_d
     * @var array $body valores unicos par envio al LTD
     * @var string $canal valor que indentifica de donde se realiza la peticion
     * 
     * 
     * @return void
     */

    public function obtenerTodas(){
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);

        $tabla = Tarifa::get();

        $pluckLtd = Ltd::where('estatus',1)
                            ->pluck('nombre','id');

        $pluckServicio = Servicio::where('estatus',1)
                ->pluck('nombre','id');

        $pluckEmpresa = Empresa::where('estatus',1)
                ->pluck('nombre','id');

        //Log::debug(print_r($tabla->toArray(),true));
        Log::debug(print_r($pluckLtd->toArray(),true));
        Log::debug(print_r($pluckServicio->toArray(),true));

        $this->tabla = $tabla;
        $this->pluckLtd= $pluckLtd;
        $this->pluckServicio = $pluckServicio;
        $this->pluckEmpresa = $pluckEmpresa;

        $this->notices[] = "Proeso con exito";

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
    }


    /**
     * Se obtienen los datos de las tarifas de los clietnes ligados al cliente
     * 
     * @author Javier Hernandez
     * @copyright 2022-2024 XpertaMexico
     * @package App\Negocio\Clientes
     * @api
     * 
     * @version 1.0.0
     * 
     * @since 1.0.0 Primera version de la funcion resumenPorCorporativo
     * 
     * @throws \LogicException
     *
     * @param array $parametros eseseses
     * 
     * @var int 
     * @var App\Negocio\Fedex_tarifas $fedexTarifa
     * @var string $cp 
     * @var string $cp_d
     * @var array $body valores unicos par envio al LTD
     * @var string $canal valor que indentifica de donde se realiza la peticion
     * 
     * 
     * @return void
     */

    public function resumenPorCorporativos(){
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        
        $tabla = mTarifa::select('empresa_id', DB::raw('count(1) as totalTarifas'),DB::raw('max(costo) as costo_max'),DB::raw('min(costo) as costo_min'), "ltds_id" , "empresa_id", "servicio_id")
        		->where('estatus',1)
        		->groupBy('empresa_id')
        		->groupBy('ltds_id')
        		->groupBy('servicio_id')
        		->orderBy('empresa_id', 'asc')
        		->get();

        $pluckLtd = Ltd::where('estatus',1)
                            ->pluck('nombre','id');

        $pluckServicio = Servicio::where('estatus',1)
                ->pluck('nombre','id');

        $pluckEmpresa = mEmpresaApi::where('estatus',1)
                ->pluck('nombre','id');

//        Log::debug(print_r($tabla->toArray(),true));
  //      Log::debug(print_r($pluckLtd->toArray(),true));
    //    Log::debug(print_r($pluckServicio->toArray(),true));
        Log::debug(print_r($pluckEmpresa->toArray(),true));


        $this->tabla = $tabla;
        $this->pluckLtd= $pluckLtd;
        $this->pluckServicio = $pluckServicio;
        $this->pluckEmpresa = $pluckEmpresa;

        $this->notices[] = "Proeso con exito";

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
    }


    /**
     * Se obtienen los datos de las tarifas del cliente por ID
     * 
     * @author Javier Hernandez
     * @copyright 2022-2024 XpertaMexico
     * @package App\Negocio\Clientes
     * @api
     * 
     * @version 1.0.0
     * 
     * @since 1.0.0 Primera version de la funcion resumenPorCliente
     * 
     * @throws \LogicException
     *
     * @param array $parametros 
     * 
     * @var int 
     * @var App\Negocio\Fedex_tarifas $fedexTarifa
     * @var string $cp 
     * @var string $cp_d
     * @var array $body valores unicos par envio al LTD
     * @var string $canal valor que indentifica de donde se realiza la peticion
     * 
     * 
     * @return void
     */

    public function resumenPorCliente($objeto){
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        
        $tabla = mTarifa::where('empresa_id',$objeto['tarifa'])
                ->get();

        $pluckLtd = Ltd::where('estatus',1)
                            ->pluck('nombre','id');

        $pluckServicio = Servicio::where('estatus',1)
                ->pluck('nombre','id');

        $pluckEmpresa = mEmpresaApi::where('estatus',1)
                ->pluck('nombre','id');



        $this->tabla = $tabla;
        $this->pluckLtd= $pluckLtd;
        $this->pluckServicio = $pluckServicio;
        $this->pluckEmpresa = $pluckEmpresa;

        $this->notices[] = "Proeso con exito";

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
    }


    public function getNotices(){
        return $this->notices;
    }


    public function getTabla(){
        return $this->tabla;
    }


    public function getPluckLtd(){
        return $this->pluckLtd;
    }

    public function getPluckEmpresa(){
        return $this->pluckEmpresa;
    }

    public function getPluckServicio(){
        return $this->pluckServicio;
    }

}