<?php

namespace App\Http\Controllers;

use App\Models\Ltd;
use App\Models\Cfg_ltd;

use App\Http\Requests\StoreLtdRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Log;

class LtdController extends Controller
{
    const INDEX_r = "ltds.index";
    const CREAR_v = "ltd.crear";
    const EDITAR_v = "ltd.editar";

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            Log::info(__CLASS__." ".__FUNCTION__);    
            $tabla = Ltd::get();
            $ltdNombre = Cfg_ltd::pluck("nombre","id");
            
            $registros = $tabla->count();
            $row = ceil($registros/3);

            return view('ltd.dashboard' 
                    ,compact("tabla", "row", "registros","ltdNombre")
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
            
            $pluckLtd = Cfg_ltd::pluck('nombre','id');
            
            return view('ltd.crear' 
                    ,compact("pluckLtd")
                );
        } catch (Exception $e) {
            Log::info(__CLASS__." ".__FUNCTION__." Exception");
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreLtdRequest $request)
    {
        Log::info(__CLASS__." ".__FUNCTION__);
        try {
            
            
            $cfgltd = Cfg_ltd::where("id",$request->nombre)->first();
            
            $data= $request->all();
            $data['nombre'] = $cfgltd->nombre;
            $data['imagen_ruta']=$cfgltd->imagen_ruta;
            
            Ltd::create($data);

            $tmp = sprintf("El registro fue habilitado exitosamente ",$request->get('nombre'));
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
     * Display the specified resource.
     *
     * @param  \App\Models\Ltd  $ltd
     * @return \Illuminate\Http\Response
     */
    public function show(Ltd $ltd)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Ltd  $ltd
     * @return \Illuminate\Http\Response
     */
    public function edit(Ltd $ltd)
    {
        try {
            Log::info(__CLASS__." ".__FUNCTION__."");
            $ltd = Ltd::findOrFail($ltd->id);
               
            Log::debug($ltd);

            $pluckLtd = Cfg_ltd::pluck('nombre','id');

            return view(self::EDITAR_v
                , compact('ltd',"pluckLtd") 
            );
       
        } catch (ModelNotFoundException $e) {
            Log::info(__CLASS__." ".__FUNCTION__." ModelNotFoundException");
            return \Redirect::back()
                ->withErrors(array($e->getMessage()))
                ->withInput();

        } catch (Exception $e) {
            Log::info(__CLASS__." ".__FUNCTION__." "."Exception");
            Log::debug( $e->getMessage() );    
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Ltd  $ltd
     * @return \Illuminate\Http\Response
     */
    public function update(StoreLtdRequest $request, Ltd $ltd)
    {
        Log::info(__CLASS__." ".__FUNCTION__);
        try {
            
            $tmp = sprintf("Actualizacion exitosa",$request->get('nombre'));
            $notices = array($tmp);

            $ltd->fill($request->post())->save();
  
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
     * @param  \App\Models\Ltd  $ltd
     * @return \Illuminate\Http\Response
     */
    public function destroy(Ltd $ltd)
    {
        
        Log::info(__CLASS__." ".__FUNCTION__);
        try {
            Log::info("Registro a Eliminar ". $ltd->id);
            $tmp = sprintf("Registro eliminado del LTD '%s', fue exitoso","nombre");
            $notices = array($tmp);

            $ltd->estatus = 0;
            $ltd->save();
  
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
