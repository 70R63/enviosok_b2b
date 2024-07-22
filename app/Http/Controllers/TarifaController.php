<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTarifaRequest;
use App\Http\Requests\UpdateTarifaRequest;
use Illuminate\Http\Request;

use App\Models\Tarifa;
use App\Models\Ltd;
use App\Models\Servicio;
use App\Models\Empresa;

use Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;

//Negocio
use App\Negocio\Clientes\Tarifas as nTarifas;

class TarifaController extends Controller
{

    const INDEX_r = "tarifas.index";

    const DASH_v = "tarifa.dashboard";
    const CREAR_v = "tarifa.crear";
    const EDITAR_v = "tarifa.editar";
    const SHOW_v = "tarifa.show";

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);

            $nTarifas = new nTarifas();
            $nTarifas->resumenPorCorporativos();
                        
            $tabla= $nTarifas->getTabla();
            $pluckLtd= $nTarifas->getPluckLtd();
            $pluckServicio= $nTarifas->getPluckServicio();
            $pluckEmpresa= $nTarifas->getPluckEmpresa();

            return view(self::DASH_v 
                    ,compact("tabla", "pluckLtd", "pluckServicio", "pluckEmpresa")
                );

        } catch (Exception $e) {
            Log::info(__CLASS__." ".__FUNCTION__." Exception");    
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        try {
            Log::info(__CLASS__." ".__FUNCTION__);    
            $tabla = array();

            $pluckEmpresa = Empresa::pluck('nombre','id');
            
            $pluckLtd = Ltd::where('estatus',1)
                                ->pluck('nombre','id');

            $pluckServicio = Servicio::where('estatus',1)
                                ->pluck('nombre','id');
            return view(self::CREAR_v 
                    ,compact("tabla","pluckLtd", "pluckServicio","pluckEmpresa")
                );
        } catch (Exception $e) {
            Log::info(__CLASS__." ".__FUNCTION__);
            Log::info("Error general ");       
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreTarifaRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreTarifaRequest $request)
    {
        Log::info(__CLASS__." ".__FUNCTION__);
        try {
            
            Tarifa::create($request->except('_token'));

            $tmp = sprintf("El registro de la nueva TARIFA '%s', fue exitoso",$request->get('nombre'));
            $notices = array($tmp);
  
            return \Redirect::route(self::INDEX_r) -> withSuccess ($notices);

        } catch(\Illuminate\Database\QueryException $ex){ 
            Log::info(__CLASS__." ".__FUNCTION__." "."QueryException");
            Log::debug($ex->getMessage()); 
    
        } catch (Exception $e) {
            Log::info(__CLASS__." ".__FUNCTION__." "."Exception");
            Log::debug( $e->getMessage() );

        }

        return \Redirect::back()
                ->withErrors(array($ex->errorInfo[2]))
                ->withInput();
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Tarifa  $tarifa
     * @return \Illuminate\Http\Response
     */
    public function show(Request $tarifa)
    {
        try {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);    
            //Log::debug($tarifa->input('tarifa'));
            Log::debug($tarifa['tarifa']);

            $nTarifas = new nTarifas();
            
            $nTarifas->resumenPorCliente($tarifa);
            
            $tabla= $nTarifas->getTabla();
            $pluckLtd= $nTarifas->getPluckLtd();
            $pluckServicio= $nTarifas->getPluckServicio();
            $pluckEmpresa= $nTarifas->getPluckEmpresa();

            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);  
            return view(self::SHOW_v 
                    ,compact("tabla","pluckLtd", "pluckServicio","pluckEmpresa")
                );  
         
        } catch (ModelNotFoundException $e) {
            Log::info(__CLASS__." ".__FUNCTION__." ModelNotFoundException");
            return \Redirect::back()
                ->withErrors(array($e->getMessage()))
                ->withInput();

        } catch (Exception $e) {
            Log::info(__CLASS__." ".__FUNCTION__);
            Log::info("Error general ");       
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Tarifa  $tarifa
     * @return \Illuminate\Http\Response
     */
    public function edit(Tarifa $tarifa)
    {
        try {
            Log::info(__CLASS__." ".__FUNCTION__);    
            $tarifa = Tarifa::findOrFail($tarifa->id);
            $pluckEmpresa = Empresa::pluck('nombre','id');
            
            $pluckLtd = Ltd::where('estatus',1)
                                ->pluck('nombre','id');

            $pluckServicio = Servicio::where('estatus',1)
                                ->pluck('nombre','id');

            return view(self::EDITAR_v 
                    ,compact("tarifa","pluckLtd", "pluckServicio","pluckEmpresa")
                );
        } catch (ModelNotFoundException $e) {
            Log::info(__CLASS__." ".__FUNCTION__." ModelNotFoundException");
            return \Redirect::back()
                ->withErrors(array($e->getMessage()))
                ->withInput();

        } catch (Exception $e) {
            Log::info(__CLASS__." ".__FUNCTION__);
            Log::info("Error general ");       
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateTarifaRequest  $request
     * @param  \App\Models\Tarifa  $tarifa
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateTarifaRequest $request, Tarifa $tarifa)
    {
        Log::info(__CLASS__." ".__FUNCTION__);
        try {
            
            $tarifa->fill($request->post())->save();
  
            $tmp = sprintf("Actualizacion de la TARIFA '%s', fue exitoso",$tarifa->id);
            $notices = array($tmp);

            return \Redirect::route(self::INDEX_r) -> withSuccess ($notices);

        } catch(\Illuminate\Database\QueryException $ex){ 
            Log::info(__CLASS__." ".__FUNCTION__." "."QueryException");
            Log::debug($ex->getMessage()); 
            return \Redirect::back()
                ->withErrors(array($ex->errorInfo[2]))
                ->withInput();

        } catch (Exception $e) {
            Log::info(__CLASS__." ".__FUNCTION__." "."Exception");
            Log::debug( $e->getMessage() );
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Tarifa  $tarifa
     * @return \Illuminate\Http\Response
     */
    public function destroy(Tarifa $tarifa)
    {
        Log::info(__CLASS__." ".__FUNCTION__);
        try {

            Log::info("Registro a Eliminar ". $tarifa->id);
            $tmp = sprintf("El Registro de la Tarifa '%s', fue eliminado exitosamente",$tarifa->id);
            $notices = array($tmp);

            $tarifa->estatus = 0;
            $tarifa->save();
  
            return \Redirect::route(self::INDEX_r) -> withSuccess ($notices);

        } catch(\Illuminate\Database\QueryException $ex){ 
            Log::info(__CLASS__." ".__FUNCTION__." "."QueryException");
            Log::debug($ex->getMessage()); 
            return \Redirect::back()
                ->withErrors(array($ex->errorInfo[2]))
                ->withInput();

        } catch (Exception $e) {
            Log::info(__CLASS__." ".__FUNCTION__." "."Exception");
            Log::debug( $e->getMessage() );
        }
    }
}
