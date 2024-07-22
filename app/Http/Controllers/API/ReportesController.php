<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\ApiController;
use Illuminate\Http\Request;
use App\Http\Requests\StoreReportesRequest;
use App\Http\Requests\UpdateReportesRequest;

use App\Models\API\Reportes;
use App\Models\API\Empresa;

use App\Negocio\Reportes\Tipo;

use Log;
use File;
use Carbon\Carbon;

class ReportesController extends ApiController
{
    /**
     * Display a listing of the resource.
     * @method GET
     *
     * @return \Illuminate\Http\Response
     */
    
    public function reportes(Request $request)
    {
        try {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            $parametros = $request->all();
            
            Log::debug(print_r($request->all(),true));

            $reporteVentas = Reportes::select("reportes.*","empresas.nombre")
                            ->tipo($parametros['tipo'])
                            ->leftJoin('empresas', 'empresas.id', '=', 'reportes.cia')
                            ->get()->toArray()
                            //->toSql()
                            ;

            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);

            $resultado = array();
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

            $reporteTipo = new Tipo();

            switch ($parametros['tipo']) {
                case 1:
                    $reporteTipo->ventas($parametros);
                    break;
                case 2:
                    $reporteTipo->repesaje($parametros);
                    break;
                default:
                    // code...
                    break;
            }
            

            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            $mensaje = "ok";
            header('Content-Type: application/csv');
//header('Content-Disposition: attachment; filename='.$filename);
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
