<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRastreosRequest;
use App\Http\Requests\UpdateRastreosRequest;
use App\Models\Rastreos;
use App\Models\API\Rastreo_peticion;
use App\Models\API\Guia;

use App\Dto\RedpackDTO; 

use App\Singlenton\Redpack as sRedpack;
use App\Singlenton\Dhl as sDhl;
//Generales 
use Log;
use Carbon\Carbon;

class RastreosController extends Controller
{

    const DASH_v = "rastreos.dashboard";
    const CREAR_v = "rastreos.crear";
    const EDITAR_v = "rastreos.editar";
    const SHOW_v = "rastreos.show";
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            Log::info(__CLASS__." ".__FUNCTION__." INICIANDO-----------------"); 

            $rastreoPeticion = array();
            foreach ( Config('ltd.general') as $key => $value) {
                Log::info("LTD ID ".$key);
                if ($key == 0) 
                    continue;
                if ($key == 6) 
                    continue;
                
                $rastreoPeticionLtd = Rastreo_peticion::where('completado',1)
                    ->where("ltd_id",$key)
                    ->latest()
                    ->first()->toArray()
                ;

                $rastreoPeticion[]= $rastreoPeticionLtd;

            }            
            Log::debug(__CLASS__." ".__FUNCTION__." FINALIZANDO----------------- ");
            return view(self::DASH_v 
                    ,compact("rastreoPeticion") 
                );
        } catch (\Exception $e) {
            Log::info(__CLASS__." ".__FUNCTION__." Exception FINALIZANDO-----------------");
            Log::debug(print_r($e->getMessage(),true));
            
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreRastreosRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreRastreosRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Rastreos  $rastreos
     * @return \Illuminate\Http\Response
     */
    public function show(Rastreos $rastreos)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Rastreos  $rastreos
     * @return \Illuminate\Http\Response
     */
    public function edit(Rastreos $rastreos)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateRastreosRequest  $request
     * @param  \App\Models\Rastreos  $rastreos
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateRastreosRequest $request, Rastreos $rastreos)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Rastreos  $rastreos
     * @return \Illuminate\Http\Response
     */
    public function destroy(Rastreos $rastreos)
    {
        //
    }



    /**
     * Busca las guias que no esten entregadas par validar su estatus 
     * 
     * @param 
     * @var 
     * 
     * @return \Illuminate\Http\Response
     */
    public function redpackAutomatico(){
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." INICIANDO-----------------");
        
        try {
            $rastreoPeticionesID = Rastreo_peticion::create( array("ltd_id"=>Config('ltd.redpack.id')) )->id;

            $guias = Guia::pendienteEntrega(Config('ltd.redpack.id'))->get()->toArray();

            $totalGuias = count($guias);
            Log::info("Total de guias revisar ".$totalGuias);

            foreach ($guias as $key => $guia) {
                Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
                Log::info("$key / $totalGuias");
                Log::debug(print_r($guia,true));

                $redpackDTO = new RedpackDTO();
                $redpackDTO->tracking($guia['tracking_number']);

                $sRedpack = new sRedpack();
                $sRedpack->trackingByNumber($redpackDTO->getBody()); 

                $update = array();
            
                if ($sRedpack->getExiteSeguimiento()) {   
                    Log::info(__CLASS__." ".__FUNCTION__." Valida seguimiento");
                    $paquete = $sRedpack->getPaquete();

                    $update = array('ultima_fecha' => $sRedpack->getUltimaFecha()
                            ,'rastreo_estatus' => Config('ltd.redpack.rastreoEstatus')[$sRedpack->getLatestStatusDetail()]
                            ,'rastreo_peso' => $paquete['peso'] 
                            ,'largo' => $paquete['largo'] 
                            ,'ancho' => $paquete['ancho'] 
                            ,'alto' => $paquete['alto']
                            ,'quien_recibio' =>  $sRedpack->getQuienRecibio()
                            ,'pickup_fecha' =>  $sRedpack->getPickupFecha()

                        );

                    Log::info(print_r($update,true));
                    Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
                    $affectedRows = Guia::where("id", $guia['id'])
                            ->update($update);
        
                    Log::debug("affectedRows -> $affectedRows");
                }else{
                    Log::info(__CLASS__." ".__FUNCTION__." Sin seguimiento");
                }
    
            }
            
            Rastreo_peticion::where('id',$rastreoPeticionesID)
                ->update(array("peticion_fin"=>Carbon::now()->toDateTimeString() 
                        ,"completado"=>true
                        ,"ltd_id" => Config('ltd.redpack.id')) 
                    );
            
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." FINALIZANDO-----------------");
            
        } catch(\Illuminate\Database\QueryException $ex){ 
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." QueryException");
            Log::debug($ex->getMessage()); 

        } catch (\Exception $ex) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." Exception");
            Log::debug(print_r($ex,true));

        }

    }//fin function redpackAutomatico()


    /**
     * Busca las guias que no esten entregadas par validar su estatus 
     * 
     * @param 
     * @var 
     * 
     * @return \Illuminate\Http\Response
     */
    public function dhlAutomatico(){
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." INICIANDO-----------------");
        
        try {
            $rastreoPeticionesID = Rastreo_peticion::create( array("ltd_id"=>Config('ltd.dhl.id')) )->id;

            $guias = Guia::pendienteEntrega(Config('ltd.dhl.id'))->get()->toArray();

            $totalGuias = count($guias);
            Log::info("Total de guias revisar ".$totalGuias);

            foreach ($guias as $key => $guia) {
                Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
                Log::info("$key / $totalGuias");
                Log::debug(print_r($guia,true));

                try {
                    Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
                    $sDhl = new sDhl();
                    $sDhl->trackingByNumber($guia['tracking_number']); 
      
                    $update = array();
                    Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);

                    if ($sDhl->getExiteSeguimiento()) {   
                        Log::info(__CLASS__." ".__FUNCTION__." Valida seguimiento");
                        $paquete = $sDhl->getPaquete();

                        $update = array('ultima_fecha' => $sDhl->getUltimaFecha()
                                ,'rastreo_estatus' => Config('ltd.dhl.rastreoEstatus')[$sDhl->getLatestStatusDetail()]
                                ,'rastreo_peso' => $paquete['peso'] 
                                ,'largo' => $paquete['largo'] 
                                ,'ancho' => $paquete['ancho'] 
                                ,'alto' => $paquete['alto']
                                ,'quien_recibio' =>  $sDhl->getQuienRecibio()
                                ,'pickup_fecha' =>  $sDhl->getPickupFecha()

                            );

                        Log::info(print_r($update,true));
                        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
                        $affectedRows = Guia::where("id", $guia['id'])
                                ->update($update);
            
                        Log::debug("affectedRows -> $affectedRows");
                    }else{
                        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." Sin seguimiento");
                    }
                    
                } catch (\GuzzleHttp\Exception\ClientException $ex) {
                    Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
                    $update = array('rastreo_estatus' => 7);

                    $affectedRows = Guia::where("id", $guia['id'])
                                ->update($update);
                    
                    Log::debug("affectedRows -> $affectedRows");

                    Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
                     
                }
                
                
    
            }//fin foreach
            
            Rastreo_peticion::where('id',$rastreoPeticionesID)
                ->update(array("peticion_fin"=>Carbon::now()->toDateTimeString() 
                        ,"completado"=>true
                        ,"ltd_id" => Config('ltd.dhl.id')) 
                    );
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." FINALIZANDO-----------------");
        
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__." ClientException");
            $response = json_decode($ex->getResponse()->getBody());
            Log::debug(print_r($response,true));
            //$mensaje = array($response->errors[0]->code);

        } catch(\Illuminate\Database\QueryException $ex){ 
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." QueryException");
            Log::debug($ex->getMessage()); 

        } catch (\Exception $ex) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." Exception");
            Log::debug(print_r($ex,true));

        }

    }//fin function redpackAutomatico()
}
