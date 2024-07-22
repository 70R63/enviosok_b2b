<?php

namespace App\Http\Controllers\Saldos;

use App\Http\Controllers\Controller;

use App\Http\Requests\StoreAjustesRequest;
use App\Http\Requests\UpdateAjustesRequest;
use Illuminate\Http\Request;

use App\Negocio\Saldos\Ajustes;

use Log;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;

class AjustesController extends Controller
{

    const INDEX_r = "ajustes.index";

    const DASH_v = "saldos.ajustes.dashboard";
    const CREAR_v = "saldos.crear";
    const EDITAR_v = "saldos.editar";
    const SHOW_v = "saldos.show";

    /**
     * Pantalla para buscar una guia y obtener generales.
     *
     * @return view DASH_v
     */
    public function index()
    {
        $tabla = array();
        $iniciarBusqueda =true;
        try {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__); 
           
            $nAjustes = new Ajustes();
            $nAjustes->tabla();
            $tabla = $nAjustes->getTabla();

            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__); 
            return view(self::DASH_v 
                    ,compact("tabla", "iniciarBusqueda")
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
                    ,compact("tabla", "iniciarBusqueda"))
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
     * @param  \App\Http\Requests\StoreAjustesRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreAjustesRequest $request)
    {
        try {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__); 
            $inputs = $request->all();
            Log::debug(print_r($inputs ,true));

            $nAjustes = new Ajustes();
            $nAjustes->insertar($inputs);

            $tabla = array();
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__); 
            $notices = array("Exitoso");
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
        
        } catch (\Exception $e) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__); 
            $mensaje=$e->getMessage();
            Log::debug(print_r($mensaje,true));
            Log::info("Error general ");       
        }

        return \Redirect::back()
                ->withErrors(array( $mensaje ))
                ->withInput();
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Saldos\Ajustes  $ajustes
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        $mensaje = "";
        $tabla = array();
        $iniciarBusqueda = false;
        $guia = array();

        try {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__); 
            Log::debug(print_r($request->all()  ,true));
            $guiaId = $request->all()["guia_id"];
            $nAjustes = new Ajustes();
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            $nAjustes->detalleGuia($guiaId); 
            $guia = $nAjustes->getGuia();

            $nAjustes->tabla();
            $tabla = $nAjustes->getTabla();

            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__); 
            
            return view(self::DASH_v 
                    ,compact("tabla", "guia","iniciarBusqueda"));


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
                    ,compact("tabla","guia","iniciarBusqueda"))->withErrors( $mensaje);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Saldos\Ajustes  $ajustes
     * @return \Illuminate\Http\Response
     */
    public function edit(Ajustes $ajustes)
    {
        abort(403, 'Unauthorized action.');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateAjustesRequest  $request
     * @param  \App\Models\Saldos\Ajustes  $ajustes
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateAjustesRequest $request, Ajustes $ajustes)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Saldos\Ajustes  $ajustes
     * @return \Illuminate\Http\Response
     */
    public function destroy(Ajustes $ajustes)
    {
        //
    }
}
