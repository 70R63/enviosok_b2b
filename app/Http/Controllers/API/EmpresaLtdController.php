<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\ApiController as BaseController;
use Illuminate\Http\Request;
use Log;
use Laravel\Sanctum\HasApiTokens;

use App\Models\EmpresaLtd;


class EmpresaLtdController extends BaseController
{
    /**
     * Login api
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        Log::info(__CLASS__." ".__FUNCTION__);
        
        $success['data'] = array();
        Log::info($success);
        return $this->successResponse($success, 'User login successfully.');
        
    }

 
    public function store(Request $request)
    {     
        Log::info(__CLASS__." ".__FUNCTION__);
        $mensaje = "";
        $empresa_id = $request->empresa_id;
        Log::debug($request);

        try {

            $objeto = EmpresaLtd::where('empresa_id',$empresa_id)->delete();
            $mensajeria = $request->except(['_token','empresa_id','clasificacion']);
            $clasificacion = $request->get('clasificacion');
            
            if ( empty($mensajeria) ) {
                $success['mensaje'] = "El cliente no cuenta con Mensajeria";
            } else {
                foreach ($mensajeria as $key => $value) {
                Log::debug("$key => $value");
                EmpresaLtd::create( array('empresa_id' => $empresa_id
                            ,'ltd_id' => $value
                            ,'tarifa_clasificacion' => $clasificacion[$key] 
                        )
                    );
                }
                $success['mensaje'] = "Asignacion exitosa!!";
            }
            

            return $this->successResponse($success, 'Asignacion de Mensajeria.');

        } catch(\Illuminate\Database\QueryException $e){ 
            Log::info(__CLASS__." ".__FUNCTION__." "."QueryException");
            Log::debug($e->getMessage()); 
            $mensaje = $e->getMessage();

        } catch (Exception $e) {
            Log::info(__CLASS__." ".__FUNCTION__." "."Exception");
            Log::debug( $e->getMessage() );
            $mensaje = $e->getMessage();
        }
        
        return $this->sendError($mensaje);
        
    }
}
