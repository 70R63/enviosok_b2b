<?php 

namespace App\Negocio\Guias;

use Log;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

//SINGLENTON
use App\Singlenton\Estafeta as sEstafeta;

//MODELS
use App\Models\API\Guia as GuiaAPI;

//NEGOCIO

//DTO

//TRAITS
use App\Traits\GettersSetters;

Class EstafetaRastreo {
	
	use GettersSetters;

	private $response;
    private $notices;
	
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
     * @since 1.0.0 Primera version de la funcion parseoRastreoManual
     * 
     * @throws
     *
     * @param  Illuminate\Http\Request  $request Recibe la paticion del cliente
     * 
     * @var array $data Se convierte el Json de la peticion a array
     * 
     * @return json Objeto con la respuesta de exito o fallo 
     */

    public function parseoRastreoManual(array $data){
    	Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);

    	$guias = $this->consultaGuiaManual($data);
        $guiaCantidad = count($guias);
        Log::info($guias);
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        if($guiaCantidad != 1)
            throw ValidationException::withMessages(array("Incongruencia de tracking o Guia no creada, Consulte a su Administrador"));
        $guia = $guias[0];

        $sEstafeta = sEstafeta::getInstance(2,"API",2);
        $sEstafeta->rastreo($guia['tracking_number']);
        $update = array();
            
        if ($sEstafeta->getExiteSeguimiento()) {   
            Log::info(__CLASS__." ".__FUNCTION__." Valida seguimiento");
            $paquete = $sEstafeta->getPaquete();

            $update = array('ultima_fecha' => $sEstafeta->getUltimaFecha()
                    ,'rastreo_estatus' => Config('ltd.estafeta.rastreoEstatus')[$sEstafeta->getLatestStatusDetail()]
                    ,'rastreo_peso' => $paquete['peso'] 
                    ,'largo' => $paquete['largo'] 
                    ,'ancho' => $paquete['ancho'] 
                    ,'alto' => $paquete['alto']
                    ,'quien_recibio' =>  $sEstafeta->getQuienRecibio()
                    ,'pickup_fecha' =>  $sEstafeta->getPickupFecha()

                );

            Log::info(print_r($update,true));

            $affectedRows = GuiaAPI::where("id", $guia['id'])
                    ->update($update);

            Log::debug("affectedRows -> $affectedRows");
            $this->notices[]= "seguimiento";
            $this->response=$sEstafeta->getResultado();
        }else{
            Log::info(__CLASS__." ".__FUNCTION__." Sin seguimiento");
            $this->notices[]= "La guia aun no cuenta seguimiento";
            $this->response=array();
        }
        /*
        foreach ($guias as $key => $value) {
            Log::info("-----".++$i."/$guiaCantidad -----");
            Log::debug($value);
            
            $sEstafeta->rastreo($value['tracking_number']);
            $update = array();
            
            if ($sEstafeta->getExiteSeguimiento()) {   
                Log::info(__CLASS__." ".__FUNCTION__." Valida seguimiento");
                $paquete = $sEstafeta->getPaquete();

                $update = array('ultima_fecha' => $sEstafeta->getUltimaFecha()
                        ,'rastreo_estatus' => Config('ltd.estafeta.rastreoEstatus')[$sEstafeta->getLatestStatusDetail()]
                        ,'rastreo_peso' => $paquete['peso'] 
                        ,'largo' => $paquete['largo'] 
                        ,'ancho' => $paquete['ancho'] 
                        ,'alto' => $paquete['alto']
                        ,'quien_recibio' =>  $sEstafeta->getQuienRecibio()
                        ,'pickup_fecha' =>  $sEstafeta->getPickupFecha()

                    );

                Log::info(print_r($update,true));

                $affectedRows = GuiaAPI::where("id", $value['id'])
                        ->update($update);
    
                Log::debug("affectedRows -> $affectedRows");
            }else{
                Log::info(__CLASS__." ".__FUNCTION__." Sin seguimiento");
            }
            
            
        } // fin foreach ($tabla as $key => $value)
		*/
		Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
    }//public function parseoApi

    /**
     * Se busca obtener la guia basada em el tracking  .
     * 
     * @author Javier Hernandez
     * @copyright 2022-2023 XpertaMexico
     * @package App\Negocio\Guias
     * @api
     * 
     * @version 1.0.0
     * 
     * @since 1.0.0 Primera version de la funcion consultaGuiaManual
     * 
     * @throws
     *
     * @param  Illuminate\Http\Request  $request Recibe la paticion del cliente
     * 
     * @var array $data Se convierte el Json de la peticion a array
     * 
     * @return array $guias Respuesta de la consulta con ltd_id y tracking
     */

    private function consultaGuiaManual(array $data){
    	Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
    	Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." Tracking ");
    	$guias = GuiaAPI::select('id','ltd_id', 'tracking_number')
    				->where('ltd_id',$data['ltd_id'])  
    				->where('tracking_number',$data['tracking_number'])  
    				->get()->toArray()
    				;
  		return $guias;
    	Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);

    }
}