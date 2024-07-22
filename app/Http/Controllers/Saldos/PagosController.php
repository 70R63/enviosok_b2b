<?php

namespace App\Http\Controllers\Saldos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Saldos\StorePagosRequest;
use App\Http\Requests\Saldos\UpdatePagosRequest;
use App\Models\Saldos\Pagos;

use App\Models\Empresa;
use App\Models\Saldos\Bancos;
use App\Models\Saldos\TipoPagos;

use App\Negocio\Saldos\Saldos;

use Log;


class PagosController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);    
            $tabla = array();

            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            return view("saldos.pagos.index"
                    ,compact("tabla")
                )

            ;
            

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
           
            $pluckEmpresa = Empresa::orderBy('nombre')->pluck('nombre','id');
            $pluckBancos = Bancos::orderBy('nombre')->pluck("nombre", "id");
            $pluckTipoPagos = TipoPagos::orderBy('id')->pluck("nombre", "id");

            return view("saldos.pagos.crear"
                ,compact("pluckEmpresa", "pluckBancos", "pluckTipoPagos")
            );
        } catch (Exception $e) {
            Log::info(__CLASS__." ".__FUNCTION__);
            Log::info("Error general ");       
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StorePagosRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StorePagosRequest $request)
    {
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $inputs = $request->except('_token');
        //dd();
        $inputs["usuario_id"] = auth()->user()->id;
        Log::info(print_r($inputs,true));
        $mensaje = "";
        try {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            Pagos::create($inputs);
            
            $saldo = new Saldos();
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            $saldo->calcular($inputs);

            $tmp = sprintf("El registro de Pago '%s'", "fue exitoso");
            $notices = array($tmp);
            
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            return \Redirect::route("pagos.index") -> withSuccess ($notices);

        } catch(\Illuminate\Database\QueryException $e){ 
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__."QueryException");
            Log::debug($e->getMessage()); 
            $mensaje= $e->getMessage();
        } catch (Exception $e) {
            Log::info(__CLASS__." ".__FUNCTION__." "."Exception");
            Log::debug( $e->getMessage() );
            $mensaje= $e->getMessage();
        }

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        return \Redirect::back()
                ->withErrors(array($mensaje))
                ->withInput();
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Saldos\Pagos  $pagos
     * @return \Illuminate\Http\Response
     */
    public function show()
    {
        try {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);    
            $tabla = array();

            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            return view("saldos.pagos.show"
                    ,compact("tabla")
                );
            

        } catch(\Illuminate\Database\QueryException $e){ 
            Log::info(__CLASS__." ".__FUNCTION__." "."QueryException");
            Log::debug($e->getMessage()); 
            $mensaje= $e->getMessage();
        } catch (Exception $e) {
            Log::info(__CLASS__." ".__FUNCTION__." "."Exception");
            Log::debug( $e->getMessage() );
            $mensaje= $e->getMessage();
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Saldos\Pagos  $pagos
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
     * @param  \App\Models\Saldos\Pagos  $pagos
     * @return \Illuminate\Http\Response
     */
    public function update(UpdatePagosRequest $request, Pagos $pagos)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Saldos\Pagos  $pagos
     * @return \Illuminate\Http\Response
     */
    public function destroy(Pagos $pagos)
    {
        //
    }
}
