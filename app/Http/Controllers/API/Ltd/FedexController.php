<?php

namespace App\Http\Controllers\API\Ltd;

use App\Http\Controllers\API\ApiController;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

use Log;
use Carbon\Carbon;

use App\Negocio\Guias\Creacion as nCreacion;


 /**
     * Controlador para la API, para usar el modelo de Negocio.
     * 
     * @author Javier Hernandez
     * @copyright 2022-2023 XpertaMexico
     * @package App\Negocio\Guias
     * @api
     * 
     * @version 1.0.0
     * 
     * @since 1.0.0 Primera version de la funcion fedexApi
     * 
     * @throws
     *
     * @param  \App\Http\Requests\UpdatesucursalRequest  $request Recibe la paticion del cliente
     * 
     * @var array $data Se convierte el Json de la peticion a array
     * 
     * 
     * @return json Objeto con la respuesta de exito o fallo 
     */

class FedexController extends ApiController
{
    
    /**
     * Controlador para la API, para usar el modelo de Negocio.
     * 
     * @author Javier Hernandez
     * @copyright 2022-2023 XpertaMexico
     * @package App\Negocio\Guias
     * @api
     * 
     * @version 1.0.0
     * 
     * @since 1.0.0 Primera version de la funcion fedexApi
     * 
     * @throws
     *
     * @param  \App\Http\Requests\UpdatesucursalRequest  $request Recibe la paticion del cliente
     * 
     * @var array $data Se convierte el Json de la peticion a array
     * 
     * 
     * @return json Objeto con la respuesta de exito o fallo 
     */
	public function terrestre(Request $request){
		try{
			Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
			$data = $request->all();
			Log::debug( print_r($data,true) );

			unset($data['token']);
			unset($data['name']);
            $data['requestedShipment']['serviceType'] = 'FEDEX_EXPRESS_SAVER';
            $data['servicio_id'] = 1;

			Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
			$nCreacion = new nCreacion();
			$nCreacion->fedexApi($data);

			$response = $nCreacion->fedex->getResponse();

			Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
		
			return $this->successResponse($response, "Exito");

        } catch (ValidationException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__.__LINE__." ValidationException");
            Log::debug(print_r($ex->getMessage(),true));
            return $this->sendError("ValidationException",$ex->getMessage(), "400");

		} catch (\Spatie\DataTransferObject\DataTransferObjectError $ex) {
            Log::info(__CLASS__." ".__FUNCTION__.__LINE__." DataTransferObjectError");
            Log::debug(print_r($ex->getMessage(),true));
            return $this->sendError("DataTransferObjectError", "consulte con su proveedor", $ex->getMessage(), "400" );

        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__." GuzzleHttp\Exception\ClientException");
            $response = $ex->getResponse()->getBody()->getContents();
            Log::debug(print_r($response,true));
            
            return $this->sendError("LTD ClientException",$response, "400");

        } catch (\GuzzleHttp\Exception\InvalidArgumentException $ex) {
        	Log::info(__CLASS__." ".__FUNCTION__.__LINE__." InvalidArgumentException");
            Log::debug(print_r($ex->getMessage(),true));
            return $this->sendError("InvalidArgumentException",$ex->getMessage(), "400");

        } catch (\GuzzleHttp\Exception\ServerException $ex) {
        	Log::info(__CLASS__." ".__FUNCTION__.__LINE__." ServerException");
            $response = $ex->getResponse()->getBody()->getContents();
            Log::debug(print_r($response,true));
            Log::debug(print_r(json_decode($response),true));
            return $this->sendError("ServerException",$ex->getMessage(), "400");            

        } catch (\LogicException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__." LogicException");
            Log::debug(print_r($ex,true));
            
            $mensaje =$ex->getMessage();
            return $this->sendError("LogicException",$ex->getMessage(), "400");


        } catch (\ErrorException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__." ErrorException");
            Log::debug(print_r($ex,true));
            
            $mensaje =$ex->getMessage();
            return $this->sendError("ErrorException",$ex->getMessage(), "400");

        } catch (\HttpException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." HttpException");
            $resultado = $ex;
            return $this->sendError("HttpException ",$ex->getMessage(), "400");
        } catch (\Exception $e) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." Exception");
            return $this->sendError("Exception",$e->getMessage(), "400");
        }
    }//fin function


    /**
     * Controlador para la API, para usar el modelo de Negocio.
     * 
     * @author Javier Hernandez
     * @copyright 2022-2023 XpertaMexico
     * @package App\Negocio\Guias
     * @api
     * 
     * @version 1.0.0
     * 
     * @since 1.0.0 Primera version de la funcion terrestreDEV
     * 
     * @throws
     *
     * @param  Illuminate\Http\Request  $request Recibe la paticion del cliente
     * 
     * @var array $data Se convierte el Json de la peticion a array
     * @var class $nCreacion Clase para el desarrollo del caso de uso 
     * @var array $response Usado para obteenr la respues del servcvio REST de FEDEX
     * 
     * 
     * @return json Objeto con la respuesta de exito o fallo 
     */
    public function terrestreDEV(Request $request){
        try{
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            $data = $request->all();
            Log::debug( print_r($data,true) );

            unset($data['token']);
            unset($data['name']);
            $data['requestedShipment']['serviceType'] = 'FEDEX_EXPRESS_SAVER';
            $data['servicio_id'] = 1;

            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            $nCreacion = new nCreacion();
            $nCreacion->fedexApi($data, "DEV");

            $response = $nCreacion->fedex->getResponse();

            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        
            return $this->successResponse($response, $nCreacion->getNotices());

        } catch (ValidationException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__.__LINE__." ValidationException");
            Log::debug(print_r($ex->getMessage(),true));
            return $this->sendError("ValidationException",$ex->getMessage(), "400");
            
        } catch (\Spatie\DataTransferObject\DataTransferObjectError $ex) {
            Log::info(__CLASS__." ".__FUNCTION__.__LINE__." DataTransferObjectError");
            Log::debug(print_r($ex->getMessage(),true));
            return $this->sendError("DataTransferObjectError", "consulte con su proveedor", $ex->getMessage(), "400" );

        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__." GuzzleHttp\Exception\ClientException");
            $response = $ex->getResponse()->getBody()->getContents();
            Log::debug(print_r($response,true));
            
            return $this->sendError("LTD ClientException",$response, "400");

        } catch (\GuzzleHttp\Exception\InvalidArgumentException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__.__LINE__." InvalidArgumentException");
            Log::debug(print_r($ex->getMessage(),true));
            return $this->sendError("InvalidArgumentException",$ex->getMessage(), "400");

        } catch (\GuzzleHttp\Exception\ServerException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__.__LINE__." ServerException");
            $response = $ex->getResponse()->getBody()->getContents();
            Log::debug(print_r($response,true));
            Log::debug(print_r(json_decode($response),true));
            return $this->sendError("ServerException",$ex->getMessage(), "400");            

        } catch (\LogicException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__." LogicException");
            Log::debug(print_r($ex,true));
            
            $mensaje =$ex->getMessage();
            return $this->sendError("LogicException",$ex->getMessage(), "400");


        } catch (\ErrorException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__." ErrorException");
            Log::debug(print_r($ex,true));
            
            $mensaje =$ex->getMessage();
            return $this->sendError("ErrorException",$ex->getMessage(), "400");

        } catch (\HttpException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." HttpException");
            $resultado = $ex;
            return $this->sendError("HttpException ",$ex->getMessage(), "400");
        } catch (\Exception $e) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." Exception");
            return $this->sendError("Exception",$e->getMessage(), "400");
        }
    }//fin function



    /**
     * Controlador para la API, para usar el modelo de Negocio.
     * 
     * @author Javier Hernandez
     * @copyright 2022-2023 XpertaMexico
     * @package App\Negocio\Guias
     * @api
     * 
     * @version 1.0.0
     * 
     * @since 1.0.0 Primera version de la funcion terrestreDEV
     * 
     * @throws
     *
     * @param  Illuminate\Http\Request  $request Recibe la paticion del cliente
     * 
     * @var array $data Se convierte el Json de la peticion a array
     * @var class $nCreacion Clase para el desarrollo del caso de uso 
     * @var array $response Usado para obteenr la respues del servcvio REST de FEDEX
     * 
     * 
     * @return json Objeto con la respuesta de exito o fallo 
     */

    public function diasigDEV(Request $request){
        try{
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            $data = $request->all();
            Log::debug( print_r($data,true) );

            unset($data['token']);
            unset($data['name']);
            $data['requestedShipment']['serviceType'] = 'STANDARD_OVERNIGHT';
            $data['servicio_id'] = 2;

            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            $nCreacion = new nCreacion();
            $nCreacion->fedexApi($data, "DEV");

            $response = $nCreacion->fedex->getResponse();

            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        
            return $this->successResponse($response, "Exito");

        } catch (ValidationException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__.__LINE__." ValidationException");
            Log::debug(print_r($ex->getMessage(),true));
            return $this->sendError("ValidationException",$ex->getMessage(), "400");

        } catch (\Spatie\DataTransferObject\DataTransferObjectError $ex) {
            Log::info(__CLASS__." ".__FUNCTION__.__LINE__." DataTransferObjectError");
            Log::debug(print_r($ex->getMessage(),true));
            return $this->sendError("DataTransferObjectError", "consulte con su proveedor", $ex->getMessage(), "400" );

        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__." GuzzleHttp\Exception\ClientException");
            $response = $ex->getResponse()->getBody()->getContents();
            Log::debug(print_r($response,true));
            
            return $this->sendError("LTD ClientException",$response, "400");

        } catch (\GuzzleHttp\Exception\InvalidArgumentException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__.__LINE__." InvalidArgumentException");
            Log::debug(print_r($ex->getMessage(),true));
            return $this->sendError("InvalidArgumentException",$ex->getMessage(), "400");

        } catch (\GuzzleHttp\Exception\ServerException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__.__LINE__." ServerException");
            $response = $ex->getResponse()->getBody()->getContents();
            Log::debug(print_r($response,true));
            Log::debug(print_r(json_decode($response),true));
            return $this->sendError("ServerException",$ex->getMessage(), "400");            

        } catch (\ErrorException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__." ErrorException");
            Log::debug(print_r($ex,true));
            
            $mensaje =$ex->getMessage();
            return $this->sendError("ErrorException",$ex->getMessage(), "400");

        } catch (\HttpException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." HttpException");
            $resultado = $ex;
            return $this->sendError("HttpException ",$ex->getMessage(), "400");
        } catch (\Exception $e) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." Exception");
            return $this->sendError("Exception",$e->getMessage(), "400");
        }
    }//fin function


    /**
     * Controlador para la API, para usar el modelo de Negocio.
     * 
     * @author Javier Hernandez
     * @copyright 2022-2023 XpertaMexico
     * @package App\Negocio\Guias
     * @api
     * 
     * @version 1.0.0
     * 
     * @since 1.0.0 Primera version de la funcion terrestreDEV
     * 
     * @throws
     *
     * @param  Illuminate\Http\Request  $request Recibe la paticion del cliente
     * 
     * @var array $data Se convierte el Json de la peticion a array
     * @var class $nCreacion Clase para el desarrollo del caso de uso 
     * @var array $response Usado para obteenr la respues del servcvio REST de FEDEX
     * 
     * 
     * @return json Objeto con la respuesta de exito o fallo 
     */

    public function diasig(Request $request){
        try{
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            $data = $request->all();
            Log::debug( print_r($data,true) );

            unset($data['token']);
            unset($data['name']);
            $data['requestedShipment']['serviceType'] = 'STANDARD_OVERNIGHT';
            $data['servicio_id'] = 2;

            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            $nCreacion = new nCreacion();
            $nCreacion->fedexApi($data);

            $response = $nCreacion->fedex->getResponse();

            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        
            return $this->successResponse($response, "Exito");

        } catch (ValidationException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__.__LINE__." ValidationException");
            Log::debug(print_r($ex->getMessage(),true));
            return $this->sendError("ValidationException",$ex->getMessage(), "400");

        } catch (\Spatie\DataTransferObject\DataTransferObjectError $ex) {
            Log::info(__CLASS__." ".__FUNCTION__.__LINE__." DataTransferObjectError");
            Log::debug(print_r($ex->getMessage(),true));
            return $this->sendError("DataTransferObjectError", "consulte con su proveedor", $ex->getMessage(), "400" );

        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__." GuzzleHttp\Exception\ClientException");
            $response = $ex->getResponse()->getBody()->getContents();
            Log::debug(print_r($response,true));
            
            return $this->sendError("LTD ClientException",$response, "400");

        } catch (\GuzzleHttp\Exception\InvalidArgumentException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__.__LINE__." InvalidArgumentException");
            Log::debug(print_r($ex->getMessage(),true));
            return $this->sendError("InvalidArgumentException",$ex->getMessage(), "400");

        } catch (\GuzzleHttp\Exception\ServerException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__.__LINE__." ServerException");
            $response = $ex->getResponse()->getBody()->getContents();
            Log::debug(print_r($response,true));
            Log::debug(print_r(json_decode($response),true));
            return $this->sendError("ServerException",$ex->getMessage(), "400");            

        } catch (\ErrorException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__." ErrorException");
            Log::debug(print_r($ex,true));
            
            $mensaje =$ex->getMessage();
            return $this->sendError("ErrorException",$ex->getMessage(), "400");

        } catch (\HttpException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." HttpException");
            $resultado = $ex;
            return $this->sendError("HttpException ",$ex->getMessage(), "400");
        } catch (\Exception $e) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." Exception");
            return $this->sendError("Exception",$e->getMessage(), "400");
        }
    }//fin function



    /**
     * Se busca obtener las tarifas de FEDEX basado en el KG .
     * 
     * @author Javier Hernandez
     * @copyright 2022-2023 XpertaMexico
     * @package App\Negocio\Guias
     * @api
     * 
     * @version 1.0.0
     * 
     * @since 1.0.0 Primera version de la funcion cotizacionDEV
     * 
     * @throws
     *
     * @param  Illuminate\Http\Request  $request Recibe la paticion del cliente
     * 
     * @var array $data Se convierte el Json de la peticion a array
     * @var class $nCreacion Clase para el desarrollo del caso de uso 
     * @var array $response Usado para obteenr la respues del servcvio REST de FEDEX
     * 
     * 
     * @return json Objeto con la respuesta de exito o fallo 
     */

    public function cotizacionDEV(Request $request){
        try{
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            $data = $request->all();
            $data['ltd_id'] = 1;
            Log::debug( print_r($data,true) );
            $servicio = $request->route()->parameter('servicio');
            Log::debug( print_r($servicio,true) );

            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            
            $nCreacion = new nCreacion();
            $nCreacion->cotizadorPorServicio($data, $servicio);


            
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        
            return $this->successResponse( $nCreacion->getResponse(), "Exito");

        } catch (ValidationException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__.__LINE__." ValidationException");
            Log::debug(print_r($ex->getMessage(),true));
            return $this->sendError("ValidationException",$ex->getMessage(), "400");
            
        } catch (\Spatie\DataTransferObject\DataTransferObjectError $ex) {
            Log::info(__CLASS__." ".__FUNCTION__.__LINE__." DataTransferObjectError");
            Log::debug(print_r($ex->getMessage(),true));
            return $this->sendError("DataTransferObjectError", "consulte con su proveedor", $ex->getMessage(), "400" );

        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__." GuzzleHttp\Exception\ClientException");
            $response = $ex->getResponse()->getBody()->getContents();
            Log::debug(print_r($response,true));
            
            return $this->sendError("LTD ClientException",$response, "400");

        } catch (\GuzzleHttp\Exception\InvalidArgumentException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__.__LINE__." InvalidArgumentException");
            Log::debug(print_r($ex->getMessage(),true));
            return $this->sendError("InvalidArgumentException",$ex->getMessage(), "400");

        } catch (\GuzzleHttp\Exception\ServerException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__.__LINE__." ServerException");
            $response = $ex->getResponse()->getBody()->getContents();
            Log::debug(print_r($response,true));
            Log::debug(print_r(json_decode($response),true));
            return $this->sendError("ServerException",$ex->getMessage(), "400");            

        } catch (\ErrorException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__." ErrorException");
            Log::debug(print_r($ex,true));
            
            $mensaje =$ex->getMessage();
            return $this->sendError("ErrorException",$ex->getMessage(), "400");

        } catch (\HttpException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." HttpException");
            $resultado = $ex;
            return $this->sendError("HttpException ",$ex->getMessage(), "400");
        } catch (\Exception $e) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." Exception");
            return $this->sendError("Exception",$e->getMessage(), "400");
        }
    }//fin function


    /**
     * Se busca obtener las tarifas de FEDEX basado en el KG .
     * 
     * @author Javier Hernandez
     * @copyright 2022-2023 XpertaMexico
     * @package App\Negocio\Guias
     * @api
     * 
     * @version 1.0.0
     * 
     * @since 1.0.0 Primera version de la funcion cotizacion
     * 
     * @throws
     *
     * @param  Illuminate\Http\Request  $request Recibe la paticion del cliente
     * 
     * @var array $data Se convierte el Json de la peticion a array
     * @var class $nCreacion Clase para el desarrollo del caso de uso 
     * @var array $response Usado para obteenr la respues del servcvio REST de FEDEX
     * 
     * 
     * @return json Objeto con la respuesta de exito o fallo 
     */

    public function cotizacion(Request $request){
        try{
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            $data = $request->all();
            $data['ltd_id'] = 1;
            Log::debug( print_r($data,true) );
            $servicio = $request->route()->parameter('servicio');
            Log::debug( print_r($servicio,true) );

            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            
            $nCreacion = new nCreacion();
            $nCreacion->cotizadorPorServicio($data, $servicio);
            
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        
            return $this->successResponse( $nCreacion->getResponse(), $nCreacion->getNotices());

        } catch (ValidationException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__.__LINE__." ValidationException");
            Log::debug(print_r($ex->getMessage(),true));
            return $this->sendError("ValidationException",$ex->getMessage(), "400");
            
        } catch (\Spatie\DataTransferObject\DataTransferObjectError $ex) {
            Log::info(__CLASS__." ".__FUNCTION__.__LINE__." DataTransferObjectError");
            Log::debug(print_r($ex->getMessage(),true));
            return $this->sendError("DataTransferObjectError", "consulte con su proveedor", $ex->getMessage(), "400" );

        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__." GuzzleHttp\Exception\ClientException");
            $response = $ex->getResponse()->getBody()->getContents();
            Log::debug(print_r($response,true));
            
            return $this->sendError("LTD ClientException",$response, "400");

        } catch (\GuzzleHttp\Exception\InvalidArgumentException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__.__LINE__." InvalidArgumentException");
            Log::debug(print_r($ex->getMessage(),true));
            return $this->sendError("InvalidArgumentException",$ex->getMessage(), "400");

        } catch (\GuzzleHttp\Exception\ServerException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__.__LINE__." ServerException");
            $response = $ex->getResponse()->getBody()->getContents();
            Log::debug(print_r($response,true));
            Log::debug(print_r(json_decode($response),true));
            return $this->sendError("ServerException",$ex->getMessage(), "400");            

        } catch (\ErrorException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__." ErrorException");
            Log::debug(print_r($ex,true));
            
            $mensaje =$ex->getMessage();
            return $this->sendError("ErrorException",$ex->getMessage(), "400");

        } catch (\HttpException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." HttpException");
            $resultado = $ex;
            return $this->sendError("HttpException ",$ex->getMessage(), "400");
        } catch (\Exception $e) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." Exception");
            return $this->sendError("Exception",$e->getMessage(), "400");
        }
    }//fin function


    /**
     * Se busca obtener las guias con un json armado pro  el cliente via API.
     * 
     * @author Javier Hernandez
     * @copyright 2022-2023 XpertaMexico
     * @package App\Negocio\Guias
     * @api
     * 
     * @version 1.0.0
     * 
     * @since 1.0.0 Primera version de la funcion creacion
     * 
     * @throws
     *
     * @param  Illuminate\Http\Request  $request Recibe la paticion del cliente
     * 
     * @var array $data Se convierte el Json de la peticion a array
     * @var class $nCreacion Clase para el desarrollo del caso de uso 
     * @var array $response Usado para obteenr la respues del servcvio REST de FEDEX
     * 
     * 
     * @return json Objeto con la respuesta de exito o fallo 
     */

    public function creacion(Request $request){

        try{

            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            $data = $request->all();
            Log::debug( print_r($data,true) );
            $servicio = $request->route()->parameter('servicios');
            switch ($servicio) {
                case 'terrestre':
                Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
                    $data['requestedShipment']['serviceType'] = 'FEDEX_EXPRESS_SAVER';
                    $data['servicio_id'] = 1;   
                    break;
                case 'diasig':
                Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
                    $data['requestedShipment']['serviceType'] = 'STANDARD_OVERNIGHT';
                    $data['servicio_id'] = 2;
                    break;
                case '2dias':
                Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
                    $data['servicio_id']=3;
                break;
                
            default:
                throw ValidationException::withMessages(array("Favor de validar tus servicios contratados"));
                break;
            }



            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            $nCreacion = new nCreacion();
            $nCreacion->fedexApi($data);

            return $this->successResponse( $nCreacion->getResponse(), $nCreacion->getNotices());

        } catch (ValidationException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__.__LINE__." ValidationException");
            Log::debug(print_r($ex->getMessage(),true));
            return $this->sendError("ValidationException",$ex->getMessage(), "400");

        } catch (\Spatie\DataTransferObject\DataTransferObjectError $ex) {
            Log::info(__CLASS__." ".__FUNCTION__.__LINE__." DataTransferObjectError");
            Log::debug(print_r($ex->getMessage(),true));
            return $this->sendError("DataTransferObjectError", "consulte con su proveedor", $ex->getMessage(), "400" );

        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__." GuzzleHttp\Exception\ClientException");
            $response = $ex->getResponse()->getBody()->getContents();
            Log::debug(print_r($response,true));
            
            return $this->sendError("LTD ClientException",$response, "400");

        } catch (\GuzzleHttp\Exception\InvalidArgumentException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__.__LINE__." InvalidArgumentException");
            Log::debug(print_r($ex->getMessage(),true));
            return $this->sendError("InvalidArgumentException",$ex->getMessage(), "400");

        } catch (\GuzzleHttp\Exception\ServerException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__.__LINE__." ServerException");
            $response = $ex->getResponse()->getBody()->getContents();
            Log::debug(print_r($response,true));
            Log::debug(print_r(json_decode($response),true));
            return $this->sendError("ServerException",$ex->getMessage(), "400");            

        } catch (\ErrorException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__." ErrorException");
            Log::debug(print_r($ex,true));
            
            $mensaje =$ex->getMessage();
            return $this->sendError("ErrorException",$ex->getMessage(), "400");

        } catch (\HttpException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." HttpException");
            $resultado = $ex;
            return $this->sendError("HttpException ",$ex->getMessage(), "400");
        } catch (\Exception $e) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." Exception");
            return $this->sendError("Exception",$e->getMessage(), "400");
        }

    }
   
}
?>