<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClienteRequest;
use App\Http\Requests\UpdateClienteRequest;
use App\Models\Cliente;
use App\Models\Ltd;
use App\Models\Servicio;
use App\Models\Empresa;

use Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ClienteController extends Controller
{
    const INDEX_r = "clientes.index";

    const DASH_v = "clientes.dashboard";
    const CREAR_v = "clientes.crear";
    const EDITAR_v = "clientes.editar";
    const SHOW_v = "clientes.show";
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            Log::info(__CLASS__." ".__FUNCTION__);    
            $tabla = Cliente::get();

            
            return view(self::DASH_v 
                    ,compact("tabla")
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

             $pluckEmpresa = Empresa::where('estatus',1)
                    ->pluck('nombre','id');
            return view(self::CREAR_v 
                    ,compact("pluckEmpresa")
                );
        } catch (Exception $e) {
            Log::info(__CLASS__." ".__FUNCTION__);
            Log::info("Error general ");       
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreDireccionRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreClienteRequest $request)
    {
        Log::info(__CLASS__." ".__FUNCTION__);
        $mensaje = "";
        try {
            
            Cliente::create($request->except('_token'));

            $tmp = sprintf("El registro de la nueva DIRECCION '%s', fue exitoso",$request->get('nombre'));
            $notices = array($tmp);
  
            return \Redirect::route(self::INDEX_r) -> withSuccess ($notices);

        } catch(\Illuminate\Database\QueryException $e){ 
            Log::info(__CLASS__." ".__FUNCTION__." "."QueryException");
            Log::debug($e->getMessage()); 
            $mensaje= $e->getMessage();
        } catch (Exception $e) {
            Log::info(__CLASS__." ".__FUNCTION__." "."Exception");
            Log::debug( $e->getMessage() );
            $mensaje= $e->getMessage();
        }

        return \Redirect::back()
                ->withErrors(array($mensaje))
                ->withInput();
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Cliente  $cliente
     * @return \Illuminate\Http\Response
     */
    public function show(Cliente $cliente)
    {
        abort(403, 'Unauthorized action.');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Cliente  $cliente
     * @return \Illuminate\Http\Response
     */
    public function edit(int $id)
    {
        $mensaje = "";
        try {

            Log::debug($id);
            Log::info(__CLASS__." ".__FUNCTION__."");
            $objeto = Cliente::findOrFail($id);
               
            $pluckEmpresa = Empresa::pluck('nombre','id');
            
            //Log::debug(print_r($pluckEmpresa,true));
            return view(self::EDITAR_v
                , compact('objeto',"pluckEmpresa") 
            );
       
        } catch (ModelNotFoundException $e) {
            Log::info(__CLASS__." ".__FUNCTION__." ModelNotFoundException");
            $mensaje = $e->getMessage();
        } catch (Exception $e) {
            Log::info(__CLASS__." ".__FUNCTION__." "."Exception");
            Log::debug( $e->getMessage() );
            $mensaje = $e->getMessage();    
        }

        return \Redirect::back()
                ->withErrors(array($mensaje))
                ->withInput();
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateDireccionRequest  $request
     * @param  \App\Models\Cliente  $cliente
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateClienteRequest $request, int $id)
    {
        Log::info(__CLASS__." ".__FUNCTION__);
        $mensaje = "";
        try {
            $objeto = Cliente::findOrFail($id);
            $datosUpdate = $request->post();
            Log::debug(print_r($datosUpdate,true));
            if ($datosUpdate['colonia']==0) {
                unset($datosUpdate['colonia']);
            }
            $objeto->fill($datosUpdate)->save();
  
            $tmp = sprintf("Actualizacion del id '%s', fue exitoso",$objeto->id);
            $notices = array($tmp);

            return \Redirect::route(self::INDEX_r) -> withSuccess ($notices);

        } catch(\Illuminate\Database\QueryException $e){ 
            Log::info(__CLASS__." ".__FUNCTION__." "."QueryException");
            Log::debug($e->getMessage()); 
            $mensaje =  $e->getMessage();
        } catch (Exception $e) {
            Log::info(__CLASS__." ".__FUNCTION__." "."Exception");
            Log::debug( $e->getMessage() );
            $mensaje =  $ex->getMessage();
        }

        return \Redirect::back()
                ->withErrors(array($mensaje))
                ->withInput();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Cliente  $cliente
     * @return \Illuminate\Http\Response
     */
    public function destroy(int $id)
    {
        Log::info(__CLASS__." ".__FUNCTION__);
        $mensaje = "";
        try {
            
            Log::info("Registro a Eliminar ". $id);

            $objeto = Cliente::findOrFail($id);
            $objeto->estatus = 0;
            $objeto->save();

            $tmp = sprintf("El Registro '%s' de la Empresa '%s', fue eliminado exitosamente",$id,$objeto->empresa);
            $notices = array($tmp);
  
            return \Redirect::route(self::INDEX_r) -> withSuccess ($notices);

        } catch(\Illuminate\Database\QueryException $e){ 
            Log::info(__CLASS__." ".__FUNCTION__." "."QueryException");
            Log::debug($e->getMessage()); 
            $mensaje = $e->getMessage();

        } catch (Exception $e) {
            Log::info(__CLASS__." ".__FUNCTION__." "."Exception");
            Log::debug( $e->getMessage() );
            $mensaje = $e->getMessage();
        }

        return \Redirect::back()
                ->withErrors(array($mensaje))
                ->withInput();
    }
}
