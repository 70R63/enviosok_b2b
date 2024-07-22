<?php

namespace App\Http\Controllers;

use App\Http\Requests\Storecfg_ltdRequest;
use App\Http\Requests\Updatecfg_ltdRequest;
use App\Models\Cfg_ltd;

use Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CfgLtdController extends Controller
{
    const INDEX_r = "mensajerias.index";

    const DASH_v = "cfgltds.dashboard";
    const CREAR_v = "cfgltds.crear";
    const EDITAR_v = "cfgltds.editar";
    const SHOW_v = "cfgltds.show";

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            Log::info(__CLASS__." ".__FUNCTION__);    
            
            $tabla = Cfg_ltd::get();
           
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

            return view(self::CREAR_v 
                );

        } catch (Exception $e) {
            Log::info(__CLASS__." ".__FUNCTION__);
            Log::info("Error general ");       
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\Storecfg_ltdRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Storecfg_ltdRequest $request)
    {
        Log::info(__CLASS__." ".__FUNCTION__);
        $mensaje = "";
        try {
            
            Cfg_ltd::create($request->except('_token'));

            $tmp = sprintf("El registro '%s', fue exitoso",$request->get('nombre'));
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
     * @param  \App\Models\cfg_ltd  $cfg_ltd
     * @return \Illuminate\Http\Response
     */
    public function show(cfg_ltd $cfg_ltd)
    {
        abort(403, 'Unauthorized action.');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\cfg_ltd  $cfg_ltd
     * @return \Illuminate\Http\Response
     */
    public function edit(int $id)
    {
        $mensaje = "";
        try {

            Log::debug($id);
            Log::info(__CLASS__." ".__FUNCTION__."");
            $objeto = Cfg_ltd::findOrFail($id);
               
            Log::debug($objeto);
            return view(self::EDITAR_v
                , compact('objeto') 
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
     * @param  \App\Http\Requests\Updatecfg_ltdRequest  $request
     * @param  \App\Models\cfg_ltd  $cfg_ltd
     * @return \Illuminate\Http\Response
     */
    public function update(Updatecfg_ltdRequest $request, int $id)
    {
         Log::info(__CLASS__." ".__FUNCTION__);
        try {
            
            $tmp = sprintf("Actualizacion de '%s', fue exitoso",$request->get('nombre'));
            $notices = array($tmp);
            $objeto = Cfg_ltd::findOrFail($id);
            $objeto->fill($request->post())->save();
  
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
     * @param  \App\Models\cfg_ltd  $cfg_ltd
     * @return \Illuminate\Http\Response
     */
    public function destroy(cfg_ltd $cfg_ltd)
    {
        //
    }
}
