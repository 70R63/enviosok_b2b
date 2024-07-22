<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCotizadorRequest;
use App\Http\Requests\UpdateCotizadorRequest;
use Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;


use App\Models\Cotizador;
use App\Models\Servicio;
use App\Models\Sucursal;
use App\Models\Cliente;
use App\Models\CP;
use App\Models\Guia;
use App\Models\Empresa;

class CotizadorController extends Controller
{

    const INDEX_r = "cotizaciones.index";

    const DASH_v = "cotizaciones.dashboard";
    const CREAR_v = "cotizaciones.crear";
    const EDITAR_v = "cotizaciones.editar";
    const SHOW_v = "cotizaciones.show";

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            Log::info(__CLASS__." ".__FUNCTION__);    

            $sucursal = Sucursal::orderby('nombre')->pluck('nombre','id');

            $cliente = Cliente::orderby('contacto')->pluck('contacto','id');

            return view(self::DASH_v 
                    ,compact( "sucursal", "cliente")
                );

        } catch (Exception $e) {
            Log::info(__CLASS__." ".__FUNCTION__." Exception");    
        }
    }

    /**
     * Crea los atributos para la vista web
     * .
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." INICIANDO-----------------");
        try {

            $objeto = $request->all();           
            Log::debug(print_r($objeto,true));
            $cliente=array();
            $sucursal= array();
            $empresaId = 0;

            Log::info(__CLASS__." ".__FUNCTION__." LINE ".__LINE__." Validacion esManual");
            if ($objeto['esManual']==="NO") {
                Log::info(__CLASS__." ".__FUNCTION__." LINE ".__LINE__." Obteniendo direccion");
               $cliente = Cliente::findOrFail($request->get("cliente_id"));
               $sucursal = Sucursal::findOrFail($request->get("sucursal_id"));

               $empresaId = $sucursal->empresa_id;
            }else{

                if ($objeto['esManual']==="SEMI") {
                    Log::info(__CLASS__." ".__FUNCTION__." LINE ".__LINE__." semi");
                    $sucursal = Sucursal::findOrFail($request->get("sucursal_id"));
                    $empresaId = $sucursal->empresa_id;
                }else{
                    Log::info(__CLASS__." ".__FUNCTION__." LINE ".__LINE__." MANUAL SI  ");
                    $empresaId = $objeto['empresa_id'];
                }

            }


            $objeto['pesos'] = explode(",", $request['pesos'][0] );
            $objeto['largos'] = explode(",", $request['largos'][0] );
            $objeto['anchos'] = explode(",", $request['anchos'][0] );
            $objeto['altos'] = explode(",", $request['altos'][0] );

            $empresa = Empresa::findOrFail($empresaId);
            Log::debug(print_r($empresa->nombre,true));
            $objeto['clienteXperta'] = $empresa->nombre;
            $objeto['empresa_id'] = $empresa->id;
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." Obteniendo Servicio");
            $servicio = Servicio::findOrFail($request->get("servicio_id"));

            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." Obteniendo precio");
            $precio = $request->get("precio");
            $ltd_nombre = $request->get("ltd_nombre");
            $piezas = $request->get("piezas_guia");
            
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." FINALIZANDO CON EXITO-----------------");  
            Log::debug(print_r($objeto,true));
            
            return view(self::CREAR_v
                , compact('cliente', 'sucursal', 'precio', 'piezas', 'ltd_nombre','objeto','servicio') 
            );

        } catch(\Illuminate\Database\QueryException $ex){ 
            Log::info(__CLASS__." ".__FUNCTION__." "."QueryException");
            Log::debug($ex->getMessage()); 
    
        } catch (Exception $e) {
            Log::info(__CLASS__." ".__FUNCTION__." "."Exception");
            Log::debug( $e->getMessage() );

        }

        Log::info(__CLASS__." ".__FUNCTION__." FINALIZANDO CON ERROR-----------------");
        return \Redirect::back()
                ->withErrors(array($ex->errorInfo[2]))
                ->withInput();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreCotizadorRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreCotizadorRequest $request)
    {
        
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Cotizador  $cotizador
     * @return \Illuminate\Http\Response
     */
    public function show(StoreCotizadorRequest $request)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Cotizador  $cotizador
     * @return \Illuminate\Http\Response
     */
    public function edit(Cotizador $cotizador)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateCotizadorRequest  $request
     * @param  \App\Models\Cotizador  $cotizador
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateCotizadorRequest $request, Cotizador $cotizador)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Cotizador  $cotizador
     * @return \Illuminate\Http\Response
     */
    public function destroy(Cotizador $cotizador)
    {
        //
    }
}
