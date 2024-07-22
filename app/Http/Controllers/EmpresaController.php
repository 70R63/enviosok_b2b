<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmpresaRequest;
use App\Http\Requests\UpdateEmpresaRequest;
use App\Models\Empresa;
use App\Models\EmpresaEmpresas;
use App\Models\Ltd;
use App\Models\EmpresaLtd;
use App\Models\Saldos\TipoPagos;
use App\Models\PlazoCreditos;
use App\Models\Saldos\Saldos as mSaldos;

use Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class EmpresaController extends Controller
{
    const INDEX_r = "empresas.index";

    const DASH_v = "empresas.dashboard";
    const CREAR_v = "empresas.crear";
    const EDITAR_v = "empresas.editar";
    const SHOW_v = "empresas.show";

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            Log::info(__CLASS__." ".__FUNCTION__);
            
            $tabla = Empresa::get();
            $empresaLtd = EmpresaLtd::get()->toArray();

            $ltdActivo = array();
            foreach ($empresaLtd as $key => $value) {
                $ltdActivo[ $value['empresa_id'] ] [$value['ltd_id']]['activo']= "true";
                $ltdActivo[ $value['empresa_id'] ] [$value['ltd_id']]['tarifa_clasificacion']= $value['tarifa_clasificacion'];

            }
            //Log::debug(print_r($ltdActivo,true));
            
            $ltds = Ltd::get();             
            
            return view(self::DASH_v 
                    ,compact("tabla","ltds", "ltdActivo")
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
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);    
            $tabla = array();
            $pluckTipoPagos = TipoPagos::orderBy('id')->pluck("nombre", "id");
            $pluckPlazoCreditos = PlazoCreditos::orderBy('id')->pluck("nombre", "id");

            return view(self::CREAR_v 
                    ,compact("tabla","pluckTipoPagos", "pluckPlazoCreditos")
                );
        } catch (Exception $e) {
            Log::info(__CLASS__." ".__FUNCTION__);
            Log::info("Error general ");       
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreEmpresaRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreEmpresaRequest $request)
    {
        Log::info(__CLASS__." ".__FUNCTION__);
        
        try {
            
            $empresa = Empresa::create($request->except('_token'));
            
            EmpresaEmpresas::create(array('id' => auth()->user()->empresa_id
                    ,'empresa_id' => $empresa->id ));

            EmpresaEmpresas::create(array('id' => $empresa->id
                    ,'empresa_id' => $empresa->id ));

            $tmp = sprintf("'%s, El registro fue exitoso",$request->get('nombre'));


            mSaldos::create(array("empresa_id" => $empresa->id));
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
     * @param  \App\Models\Empresa  $empresa
     * @return \Illuminate\Http\Response
     */
    public function show(Empresa $empresa)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Empresa  $empresa
     * @return \Illuminate\Http\Response
     */
    public function edit(int $id)
    {
        $mensaje = "";
        try {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            $objeto = Empresa::findOrFail($id);
            $pluckTipoPagos = TipoPagos::orderBy('id')->pluck("nombre", "id");
            $pluckPlazoCreditos = PlazoCreditos::orderBy('id')->pluck("nombre", "id");
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            //Log::debug($objeto);
            return view(self::EDITAR_v
                , compact('objeto',"pluckTipoPagos", "pluckPlazoCreditos") 
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
     * @param  \App\Http\Requests\UpdateEmpresaRequest  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateEmpresaRequest $request, int $id)
    {
        Log::info(__CLASS__." ".__FUNCTION__);

        $mensaje = "";
        try {
            $objeto = Empresa::findOrFail($id);
            $objeto->fill($request->post())->save();
  
            $tmp = sprintf("Actualizacion del id %s, '%s', fue exitoso",$objeto->id, $objeto->nombre);
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
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(int $id)
    {
        Log::info(__CLASS__." ".__FUNCTION__);
        $mensaje = "";
        try {
            
            Log::info("Registro a Eliminar ". $id);

            $objeto = Empresa::findOrFail($id);
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
