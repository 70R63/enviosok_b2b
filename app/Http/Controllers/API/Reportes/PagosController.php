<?php

namespace App\Http\Controllers\API\Reportes;

use App\Http\Controllers\API\ApiController;
use Illuminate\Http\Request;

use Log;
use File;
use Carbon\Carbon;

use App\Negocio\Reportes\Pagos AS nPagos; 

class PagosController extends ApiController
{
    /**
     * Display a listing of the resource.
     * @method GET
     *
     * @return \Illuminate\Http\Response json
     */

    public function index()
    {
        try {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            $nPagos = new nPagos();
            $nPagos->tablaResumen();
            $reporteVentas = $nPagos->getTabla();

            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            
            
            $mensaje = "ok";
            return $this->successResponse($reporteVentas, $mensaje);    

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


    /**
     * Display a listing of the resource.
     * 
     * @method POST
     *
     * @return \Illuminate\Http\Response
     */
    
    public function creacion(Request $request)
    {
        try {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            $parametros = $request->all();
            Log::debug(print_r($parametros,true));

            $nPagos = new nPagos();
            $nPagos->creacionCsv($parametros);
           
       Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            $mensaje = "ok";
            return $this->successResponse(array(), $mensaje);    

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