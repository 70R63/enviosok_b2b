<?php

namespace App\Http\Controllers\API\Saldos;

use App\Http\Controllers\API\ApiController;
use Illuminate\Http\Request;

use App\Models\Saldos\Pagos;
use App\Models\Saldos\PagoResumens;

use Log;

use Carbon\Carbon;

class PagosController extends ApiController
{
    /**
     * Se obtiene los registros de lo sdeposties de la una vista
     * @method GET
     *
     * @return \Illuminate\Http\Response
     */
    
    public function tablaPagosResumen()
    {
        try {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
           
            $pagoResumens = PagoResumens::orderBy('nombre')
                            ->empresas()
                            ->get()->toArray();

            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            Log::info(auth()->user()->empresa_id);
            
            $resultado = array();
            $mensaje = "ok";
            return $this->successResponse($pagoResumens, $mensaje);    

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
     * Se obtiene los registros de lo sdeposties de la una vista
     * @method GET
     *
     * @return \Illuminate\Http\Response
     */
    
    public function tablaPagos($empresa_id)
    {
        try {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
           
            $pagos = Pagos::select("*","pagos.id AS pago_id","empresas.nombre AS empresa_nombre", "bancos.nombre AS banco_nombre", "users.name AS users_nombre",\DB::raw('DATE_FORMAT(pagos.created_at, "%Y-%m-%d") as createdAt'))

                ->where("pagos.empresa_id",$empresa_id)
                ->joinEmpresa()
                ->joinBancos()
                ->joinUsuario()
                ->get()->toArray()
                ;
            #Log::debug(print_r($pagoResumens, true));
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);

            $resultado = array();
            $mensaje = "ok";
            return $this->successResponse($pagos, $mensaje);    

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
