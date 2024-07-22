<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoresucursalRequest;
use App\Http\Requests\UpdatesucursalRequest;
use App\Models\Sucursal;
use App\Models\Empresa;

use Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class SucursalController extends Controller
{

    const INDEX_r = "sucursales.index";

    const DASH_v = "sucursales.dashboard";
    const CREAR_v = "sucursales.crear";
    const EDITAR_v = "sucursales.editar";
    const SHOW_v = "sucursales.show";
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {

        try {
            Log::info(__CLASS__." ".__FUNCTION__);    
            $tabla = Sucursal::get();

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
           
            $pluckEmpresa = Empresa::pluck('nombre','id');

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
     * @param  \App\Http\Requests\StoresucursalRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoresucursalRequest $request)
    {
        Log::info(__CLASS__." ".__FUNCTION__);
        $mensaje = "";
        try {
            
            Sucursal::create($request->except('_token'));

            $tmp = sprintf("El registro de la REMITENTE '%s', fue exitoso",$request->get('nombre'));
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
     * @param  \App\Models\sucursal  $sucursal
     * @return \Illuminate\Http\Response
     */
    public function show(sucursal $sucursal)
    {
        abort(403, 'Unauthorized action.');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\sucursal  $sucursal
     * @return \Illuminate\Http\Response
     */
    public function edit(int $id)
    {
        $mensaje = "";
        try {

            Log::debug($id);
            Log::info(__CLASS__." ".__FUNCTION__."");
            $objeto = Sucursal::findOrFail($id);

            $pluckEmpresa = Empresa::pluck('nombre','id');
               
            Log::debug($objeto);
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
     * @param  \App\Http\Requests\UpdatesucursalRequest  $request
     * @param  \App\Models\sucursal  $sucursal
     * @return \Illuminate\Http\Response
     */
    public function update(UpdatesucursalRequest $request, int $id)
    {
        Log::info(__CLASS__." ".__FUNCTION__);
        $mensaje = "";
        try {
            $objeto = Sucursal::findOrFail($id);
            $datosUpdate = $request->post();
            Log::debug(print_r($datosUpdate,true));
            if ($datosUpdate['colonia']==0) {
                unset($datosUpdate['colonia']);
            }
            $objeto->fill($datosUpdate)->save();
  
            $tmp = sprintf("Actualizacion del id '%s', fue exitoso",$id);
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
     * @param  \App\Models\sucursal  $sucursal
     * @return \Illuminate\Http\Response
     */
    public function destroy(int $id)
    {
        Log::info(__CLASS__." ".__FUNCTION__);
        $mensaje = "";
        try {
            
            Log::info("Registro a Eliminar ". $id);

            $objeto = Sucursal::findOrFail($id);
            $objeto->estatus = 0;
            $objeto->save();

            $tmp = sprintf("El Registro '%s' de la Sucursal '%s', fue eliminado exitosamente",$id,$objeto->nombre);
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
