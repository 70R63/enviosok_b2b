<?php

namespace App\Http\Controllers;

use App\Models\Guia;
use App\Models\Empresa;
use App\Models\EmpresaLtd;
use App\Models\Sucursal;
use App\Models\Cliente;
use App\Models\Cfg_ltd;
use App\Models\Servicio;
use App\Models\Ltd;


use App\Mail\GuiaCreada;

use App\Dto\EstafetaDTO;
use App\Dto\FedexDTO;
use App\Dto\Guia as GuiaDTO;

use App\Singlenton\Estafeta as sEstafeta;
use App\Singlenton\Fedex;

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Log;
use Mail;
use Config;
use Redirect;

use Carbon\Carbon;

class GuiaRetornoController extends Controller
{

    const INDEX_r = "guia.index";

    const DASH_v = "guia.dashboard";
    const CREAR_v = "cotizaciones.crear";
    const EDITAR_v = "guia.editar";
    const SHOW_v = "guia.show";

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        try {
            Log::info(__CLASS__." ".__FUNCTION__." INICIANDO -----");

            Log::debug(print_r($request->all(),true));
            $guia = Guia::where('id',$request['guia_id'])->get()->toArray()[0];   
            Log::debug($guia);
            
            //retorno Invertir Cliente por Sucursal
            $ltd_id = $guia['ltd_id'];
            $sucursal = Cliente::findOrFail($guia['cia_d']);
            $cliente = Sucursal::findOrFail($guia['cia']);
            $servicio = Servicio::findOrFail($guia['servicio_id']);
            $ltd = Ltd::findOrFail($guia['ltd_id']);
            $empresa = Empresa::findOrFail($guia["empresa_id"]);
           
            $ltd_nombre = $ltd['nombre'];
            $precio = $guia['precio'];
            $piezas = $guia['piezas'];

            $objeto['clienteXperta'] = $empresa->nombre;
            $objeto['peso_facturado'] = $guia['peso'];
            $objeto['valor_envio_r'] = $guia['valor_envio'];
            $objeto['costo_seguro'] = $guia['seguro'];
            $objeto['esManual'] = "RETORNO" ;
            $objeto['ltd_id'] = $ltd_id;
            
            $objeto['bSeguro'] = $guia['seguro'];;
            $objeto['contenido_r'] = $guia['contenido'];
            $objeto['extendida_r'] = $guia['extendida'];

            [$objeto['largos'],$objeto['anchos'], $objeto['altos']] = explode('x', $guia['dimensiones']);

            //$objeto['cp_d_manual'] = "55555";
            $objeto['empresa_id'] = $guia["empresa_id"];


            Log::info(__CLASS__." ".__FUNCTION__." FINALIZANDO-----");

            return view(self::CREAR_v
                , compact('cliente', 'sucursal', 'precio', 'piezas', 'ltd_nombre','objeto','servicio') 
            );
        } catch(\Illuminate\Database\QueryException $e){ 
            Log::info(__CLASS__." ".__FUNCTION__." "."QueryException");
            $mensaje = array($e->getMessage());
            Log::debug(print_r($e,true));

        } catch (\Exception $e) {
            Log::info(__CLASS__." ".__FUNCTION__." Exception");
            $mensaje = array($e->getMessage());
            Log::debug(print_r($e,true));
                  
        }

        return back()
            ->with('dangers',$mensaje)
            ->withInput();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Guia  $guia
     * @return \Illuminate\Http\Response
     */
    public function show(Guia $guia)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Guia  $guia
     * @return \Illuminate\Http\Response
     */
    public function edit(Guia $guia)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Guia  $guia
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Guia $guia)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Guia  $guia
     * @return \Illuminate\Http\Response
     */
    public function destroy(Guia $guia)
    {
        //
    }

}
