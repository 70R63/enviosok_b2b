<?php

namespace App\Http\Controllers\Guias;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guias\StoreMasivasRequest;
use App\Http\Requests\Guias\UpdateMasivasRequest;

use App\Models\Guias\Masivas;

use App\Negocio\Guias\Masivas AS nMasivas;

use Log;

class MasivasController extends Controller
{

    const INDEX_r = "guias.masivas.index";

    const DASH_v = "guia.masivas.dashboard";
    const CREAR_v = "guias.masiva";
    const EDITAR_v = "guias.masiva";
    const SHOW_v = "guias.masiva";

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $tabla = array();
        $iniciarBusqueda =true;
        try {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__); 
            $nMasivas = new nMasivas();
            $nMasivas->tabla();
            $tabla = $nMasivas->getTabla();

            $notices = $nMasivas->getMensajes();

            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__); 
            return view(self::DASH_v 
                    ,compact("tabla")
                );


        } catch (ModelNotFoundException $e) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__); 
            $mensajeInterno=$e->getMessage();
            Log::debug(print_r($mensajeInterno,true));
            Log::info("ModelNotFoundException");       
            $mensaje = "ModelNotFoundException - Favor de buscar a tu administrador ";
        
        } catch (QueryException $e) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__); 
            $mensajeInterno=$e->getMessage();
            Log::debug(print_r($mensajeInterno,true));
            Log::info("QueryException");       
            $mensaje = "QueryException - Favor de buscar a tu administrador ";
        
        } catch (\Exception $e) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__); 
            $mensaje=$e->getMessage();
            Log::debug(print_r($mensaje,true));
            Log::info("Error general ");       
        }

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        return view(self::DASH_v 
                    ,compact("tabla"))
                ->withErrors( $mensaje);
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
     * @param  \App\Http\Requests\StoreMasivasRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreMasivasRequest $request)
    {
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        Log::debug($request->all());

        try {
            
            $nMasivas = new nMasivas();
            $nMasivas->crear($request->fileGuiasMasivas);
            
            $notices = $nMasivas->getMensajes();
  
            return \Redirect::route(self::INDEX_r) -> withSuccess ($notices);
        } catch (ModelNotFoundException $e) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__); 
            $mensajeInterno=$e->getMessage();
            Log::debug(print_r($mensajeInterno,true));
            Log::info("ModelNotFoundException");       
            $mensaje = "ModelNotFoundException - Favor de buscar a tu administrador ";
        

        } catch (QueryException $e) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__); 
            $mensajeInterno=$e->getMessage();
            Log::debug(print_r($mensajeInterno,true));
            Log::info("QueryException");       
            $mensaje = "QueryException - Favor de buscar a tu administrador ";
    
         } catch (Exception $e) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__); 
            $mensaje=$e->getMessage();
            Log::debug(print_r($mensaje,true));
            Log::info("Error general ");   

        }

        return \Redirect::back()
                ->withErrors(array($mensaje))
                ->withInput();
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Guias\Masivas  $masivas
     * @return \Illuminate\Http\Response
     */
    public function show(Masivas $masivas)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Guias\Masivas  $masivas
     * @return \Illuminate\Http\Response
     */
    public function edit(Masivas $masivas)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateMasivasRequest  $request
     * @param  \App\Models\Guias\Masivas  $masivas
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateMasivasRequest $request, Masivas $masivas)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Guias\Masivas  $masivas
     * @return \Illuminate\Http\Response
     */
    public function destroy(Masivas $masivas)
    {
        //
    }
}
