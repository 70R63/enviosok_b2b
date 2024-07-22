<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\ApiController as BaseController;
use Illuminate\Http\Request;
use Log;
use Laravel\Sanctum\HasApiTokens;
use DB;
use Carbon\Carbon;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

use App\Models\Sucursal;
use App\Models\Cliente;

use App\Negocio\Guias\Cotizacion as nCotizacion;
use App\Negocio\Guias\EstafetaCreacion as nEstafetaCreacion;
use App\Negocio\Guias\Creacion as nCreacion;




class CotizacionController extends BaseController
{
    /**
     * Login api
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        Log::debug($request);

        
        $nCotizacion = new nCotizacion();
        $nCotizacion->base($request);
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $tabla = $nCotizacion->getTabla();

        Log::debug(__CLASS__." ".__FUNCTION__." ".__LINE__." Revision de tabla");
        Log::debug(print_r($tabla,true));
        $success['data'] = $tabla;
        $success['saldo'] = $nCotizacion->getSaldo();
        $success['tipoPagoId'] = $nCotizacion->getTipoPagoId();
       
        return $this->successResponse($success, 'Cotizacion exitosa.');
        
    }

    /**
     * Login api
     *
     * @return \Illuminate\Http\Response
     */
    public function cp(Request $request){
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        Log::info($request);
        $modelo = $request->get('modelo');

        if ("Sucursal" == $modelo) {
            $datos = Sucursal::where("id",$request->id);
        } else {
            $datos = Cliente::where("id",$request->id);
        }
        $resultado = $datos->get()
                ->toArray();
        
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        Log::info($resultado);
        return $this->successResponse($resultado, 'User login successfully.');
        
    }

    public function store(Request $request)
    {
        $success['name'] = "nombre";
        
        return $this->successResponse($success, 'User login successfully.');
    }

    private function queryBaseTarifa()
    {
        $success['name'] = "nombre";
        
        return $this->successResponse($success, 'User login successfully.');
    }

    /**
     * Se busca obtener las tarifas como si se realizara una gaui en comparacion de index que obtiene tarifas y en un js se hacen calculos.
     * 
     * @author Javier Hernandez
     * @copyright 2022-2023 XpertaMexico
     * @package App\Http\Controllers\API
     * @api
     * 
     * @version 1.0.0
     * 
     * @since 1.0.0 Primera version de la funcion cotizaciones
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

    public function cotizaciones(Request $request){
        try{
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);

            $data =$request->all();
                if(empty($data))
                    throw ValidationException::withMessages(array("Favor de validar tu body"));
           
            $data['canal'] = "API";
            $data['esManual'] = "API";
            $data['sucursal_id']=0;
            $data['numero_solicitud'] = Carbon::now()->timestamp;
            
            Log::debug(__CLASS__." ".__FUNCTION__." ".__LINE__);
          
           
            $servicio = $request->route()->parameter('servicios');
            $ltd = $request->route()->parameter('ltds');
            
            
            switch ($servicio) {
                case 'terrestre':
                    $data['servicio_id']=1; 
                    break;
                case 'diasig':
                    $data['servicio_id']=2;
                    break;
                case '2dias':
                    $data['servicio_id']=3;
                break;
                
                default:
                    throw ValidationException::withMessages(array("Favor de validar tu servicio"));
                    break;
               
            }
            Log::debug(__CLASS__." ".__FUNCTION__." ".__LINE__);
            //Validar cambio posterior apra evitar esta reasignacion
            $data['valor_envio']= $data['valor_declarado'];
            Log::info($data);
            $objetoGeneral = null;
            switch ($ltd) {
                case "estafeta":
                Log::debug(__CLASS__." ".__FUNCTION__." ".__LINE__." $ltd");
                    $data['ltd_id']= 2;
                    $nEstafetaCreacion = new nEstafetaCreacion();
                    $nEstafetaCreacion->soloCotizacion($data);
                    $objetoGeneral = $nEstafetaCreacion;
                    break;
                case "fedex":
                Log::debug(__CLASS__." ".__FUNCTION__." ".__LINE__." $ltd");
                    $data['ltd_id']= 1;

                    $nCreacion = new nCreacion();
                    //$nCreacion->cotizadorPorServicio($data, $servicio);
                    $nCreacion->soloCotizacion($data, $servicio);
                    $objetoGeneral = $nCreacion;
                    break;
                
                default:
                    throw ValidationException::withMessages(array("La paqueteria no existe favor de validar con el Administrador"));
                    break;
            }
            

            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);

            return $this->successResponse( $objetoGeneral->getResponse()                ,$objetoGeneral->getNotices() );

        } catch (ValidationException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__.__LINE__." ValidationException");
            Log::debug(print_r($ex->getMessage(),true));
            return $this->sendError("ValidationException",$ex->getMessage(), "400");
        
        } catch ( ModelNotFoundException $ex) {
            Log::info(__CLASS__." ".__FUNCTION__.__LINE__." ModelNotFoundException");
            Log::debug(print_r($ex,true));
            return $this->sendError("ModelNotFoundException","Favor de contactar al proveedor", "400");
            
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
            Log::debug($e->getMessage());
            return $this->sendError("Exception","Favor de validar con tu proveedor", "400");
        }
    }//fin function
}
