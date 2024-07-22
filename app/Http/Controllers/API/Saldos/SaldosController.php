<?php

namespace App\Http\Controllers\API\Saldos;

#GENERAL 
use App\Http\Controllers\API\ApiController;
use Illuminate\Http\Request;
use Log;
use Carbon\Carbon;


#NEGOCIO 
use App\Negocio\Saldos\Saldos as nSaldos;
#MODELS



class SaldosController extends ApiController
{

	/**
     * Se busca obtenre el saldo de la empresa ligada al usuaio
     * 
     * @author Javier Hernandez
     * @copyright 2022-2024 XpertaMexico
     * @package App\Http\Controllers\API\Saldos
     * @api
     * 
     * @version 1.0.0
     * 
     * @since 1.0.0 Primera version de la funcion porEmpresa
     * 
     * @throws
     *
     * @param array $parametros eseseses
     * 
     * @var int 
     * 
     * 
     * @return json Objeto con la respuesta de exito o fallo 
     */

	public function porEmpresa(Request $request){
		
		try {
			Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." INICIO---------------");
			Log::debug(print_r($request->all(),true));
			$nSaldos = new nSaldos();
			$monto = $nSaldos->porEmpresa($request->empresa_id);
			
			Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." monto = $monto");

	        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." FIN---------------");
	        $mensaje = "ok";
            return $this->successResponse(array($monto), $mensaje);

		} catch (\Spatie\DataTransferObject\DataTransferObjectError $ex) {
            Log::info(__CLASS__." ".__FUNCTION__." DataTransferObjectError");
            Log::debug(print_r($ex->getMessage(),true));
            return $this->sendError("tracking :$trackingNumber, consulte con su proveedor", $ex->getMessage(), "400" );

        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__."GuzzleHttp\Exception\ClientException");
             $response = $ex->getResponse()->getBody()->getContents();
            Log::debug(print_r($response,true));
            
            return $this->sendError("tracking :$trackingNumber",$response, "400");

        } catch (\GuzzleHttp\Exception\InvalidArgumentException $ex) {
            Log::debug($ex );
            return $this->sendError("tracking :$trackingNumber",$ex->getMessage(), "400");

        } catch (\GuzzleHttp\Exception\ServerException $ex) {
            $response = $ex->getResponse()->getBody()->getContents();
            Log::debug(print_r($response,true));
            Log::debug(print_r(json_decode($response),true));
            return $this->successResponse(json_decode($response), "ServerException");            

        } catch (\InvalidArgumentException $ex) {
            Log::debug($ex );
            return $this->successResponse("Response", "InvalidArgumentException","400");

        } catch (\ErrorException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__." ErrorException");
            Log::debug(print_r($ex,true));
            
            $mensaje =$ex->getMessage();
            return $this->sendError("ErrorException ",$ex->getMessage(), "400");

        } catch (\HttpException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__." HttpException");
            $resultado = $ex;
            $mensaje = "La guia no pudo ser creada";
            return $this->sendError("tracking :$trackingNumber",$mensaje, "400");
        } catch (\Exception $e) {
            Log::info(__CLASS__." ".__FUNCTION__." Exception");
            return $this->sendError("Exception",$e->getMessage(), "400");
        }
    }// Fin public function porEmpresa



}