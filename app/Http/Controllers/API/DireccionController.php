<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\ApiController;
use Illuminate\Http\Request;
use App\Models\Cliente;
use App\Models\Sucursal;
use App\Models\API\Sucursal as SucursalApi;
use App\Models\API\Cliente as ClienteApi;

use Log;

class DireccionController extends ApiController
{
    /**
     * Index,  api
     *
     * @return \Illuminate\Http\Response
     */
    public function index($tipo)
    {
        Log::info(__CLASS__." ".__FUNCTION__." INICIANDO-----------------");
        Log::debug($tipo);

        try {

            switch ($tipo) {
                case 'destinatario':
                    //Destino
                    $tabla = Cliente::get()->toArray();
                    break;

                case 'remitente':
                    //Origen
                    $tabla = Sucursal::get()->toArray();
                    break;
                
                default:
                    Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
                    $sucursal = SucursalApi::where('id',$tipo)
                            ->get()->toArray();

                    $tabla = ClienteApi::where('empresa_id',$sucursal[0]['empresa_id'])
                        ->orderBy('nombre')
                        ->get()->toArray();
                    Log::debug(print_r($tabla,true));
                    // code...
                    break;
            }


            $success['mensaje'] = "Asignacion exitosa";

            return $this->successResponse($tabla, 'User login successfully.');

        } catch(\Illuminate\Database\QueryException $e){ 
            Log::info(__CLASS__." ".__FUNCTION__." QueryException");
            Log::debug($e->getMessage()); 
            $mensaje = $e->getMessage();

        } catch (\Exception $e) {
            Log::info(__CLASS__." ".__FUNCTION__." Exception");
            Log::debug( $e->getMessage() );
            $mensaje = $e->getMessage();
        }
        Log::info(__CLASS__." ".__FUNCTION__." FINALIZANDO-----------------");
        return $this->sendError($mensaje);
        
    }
}
