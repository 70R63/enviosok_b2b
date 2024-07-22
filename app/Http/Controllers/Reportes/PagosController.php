<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reportes\StorePagosRequest;
use App\Http\Requests\Reportes\UpdatePagosRequest;

use App\Negocio\Reportes\Pagos as nPagos;

use Log;

class PagosController extends Controller
{

    const INDEX_r = "externas.index";

    const DASH_v = "reportes.pagos.index";
    const CREAR_v = "saldos.crear";
    const EDITAR_v = "saldos.editar";
    const SHOW_v = "saldos.show";
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
           
            $nPagos = new nPagos();
            $nPagos->cfgBancos();
            $bancos = $nPagos->getBancos();
            Log::info(print_r($bancos,true));         

            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__); 
            return view(self::DASH_v 
                    ,compact("bancos")
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
     * @param  \App\Http\Requests\StorePagosRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StorePagosRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Reportes\Pagos  $pagos
     * @return \Illuminate\Http\Response
     */
    public function show(Pagos $pagos)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Reportes\Pagos  $pagos
     * @return \Illuminate\Http\Response
     */
    public function edit(Pagos $pagos)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdatePagosRequest  $request
     * @param  \App\Models\Reportes\Pagos  $pagos
     * @return \Illuminate\Http\Response
     */
    public function update(UpdatePagosRequest $request, Pagos $pagos)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Reportes\Pagos  $pagos
     * @return \Illuminate\Http\Response
     */
    public function destroy(Pagos $pagos)
    {
        //
    }
}
