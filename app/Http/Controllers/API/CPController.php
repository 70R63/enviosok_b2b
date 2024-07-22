<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\ApiController;
use Illuminate\Http\Request;
use App\Http\Requests\StoreCPRequest;
use App\Http\Requests\UpdateCPRequest;
use App\Models\CP;

use Log;

class CPController extends ApiController
{
    /**
     * Muestr a una lista de colonias basado en el CP.
     *
     * @return \Illuminate\Http\Response
     */
    public function colonias(Request $request)
    {
        Log::info(__CLASS__." ".__FUNCTION__." INICIANDO-----------------");
        Log::debug(print_r($request->all(),true));
        try {
            $resultado = CP::where('cp', 'like', $request['cp'].'%')
                    ->get();

            Log::debug(print_r($resultado->toArray(),true));

            Log::info(__CLASS__." ".__FUNCTION__." FINALIZANDO-----------------");
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
