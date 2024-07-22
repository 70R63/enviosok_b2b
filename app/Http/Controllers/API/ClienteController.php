<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\ApiController;
use Illuminate\Http\Request;
use App\Models\Empresa;
use App\Models\EmpresaEmpresas;


use Log;

class ClienteController extends ApiController
{
    /**
     * Muestr a una lista de colonias basado en el CP.
     *
     * @return \Illuminate\Http\Response
     */
    public function clientes(Request $request)
    {
        Log::info(__CLASS__." ".__FUNCTION__." INICIANDO-----------------");
        Log::debug(print_r($request->all(),true));
        try {

            $empresas = EmpresaEmpresas::where('id',auth()->user()->empresa_id)
                ->pluck('empresa_id')->toArray();

            $resultado = Empresa::whereIN('id',$empresas)
                    ->get()->toArray();
           
            //Log::debug(print_r($resultado,true));
            //$resultado = array();
            $mensaje = "ok";
            return $this->successResponse($resultado, $mensaje);    

        } catch (\InvalidArgumentException $ex) {
            Log::debug($ex );
            $mensaje = $ex->getMessage();

        } catch (\ErrorException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__." ErrorException");
            Log::debug(print_r($ex,true));
            
            $mensaje =$ex->getMessage();

        } catch (\HttpException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__." HttpException");
            $resultado = $ex;
            $mensaje = $ex->getMessage();
        } catch (\Exception $e) {
            Log::info(__CLASS__." ".__FUNCTION__." Exception");
            Log::debug(print_r($e->getMessage(),true ));
           $mensaje = $e->getMessage();
        }
        Log::info(__CLASS__." ".__FUNCTION__." FINALIZANDO-----------------");
        return $this->sendError("Exception",$mensaje, "400");
    }
}
