<?php

namespace App\Singlenton;

use GuzzleHttp\Client;
use Log;
use Carbon\Carbon;
use Config;


/**
* Singlenton para contriuir una peticion de creacion de guia y validacion del token
* 
* @param string $token
* @param string $baseUri
*  
*/

class Dhl {

    private static $instance;

    private $token;
    private $baseUri;

    public $documento = 0; 
    private $trackingNumber = 0;
    private $scanEvents = array();
    private $paquete = array();
    private $exiteSeguimiento = false;
    private $quienRecibio = "--------"; 
    private $latestStatusDetail;
    private $fechaEntrega; //Validar uso en la case redpack y no en el controller
    private $pickupFecha;


    public function __construct(){

        
    }


    /**
     * Cliente busca crear una funcion donde se inicialice la peticion via guzzle.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return GuzzleHttp\Client $response
     */

    private function clienteRest($body,$metodo = 'GET', $servicio, array $headers){
        Log::debug(__CLASS__." ".__FUNCTION__." ".__LINE__." INICIANDO-----------------");

        $client = new Client(['base_uri' => $this->baseUri]);
	
	Log::debug(print_r($body,true));	
        $bodyJson = json_encode($body);
        Log::debug(print_r($bodyJson,true));

        Log::debug(__CLASS__." ".__FUNCTION__." ".__LINE__." FINALIZANDO-----------------");
        return $client->request($metodo,$servicio , [
                    'headers'   => $headers
                    ,'body'     => $bodyJson
                ]);
    }


   /**
     * documentation es la actividad para registrar nuestra peticion.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    public function documentation($body){
        Log::debug(__CLASS__." ".__FUNCTION__." ".__LINE__);

        $basic = sprintf("%s:%s", Config('ltd.dhl.api_key'), Config('ltd.dhl.secret') );
        $authorization = sprintf("Basic %s",base64_encode($basic));

        $headers = ['Authorization' => $authorization  
                    ,'Content-Type' => 'application/json'
                ];

        $this->baseUri = Config('ltd.dhl.base_uri');
        Log::debug(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $response = $this->clienteRest($body, 'POST', Config('ltd.dhl.shipment.uri') , $headers);

        Log::debug(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $contenido = json_decode($response->getBody()->getContents());
        
        Log::debug(print_r($contenido,true) );
        $objResponse = $contenido;

        $packages = $objResponse->packages[0];
        
        $this->trackingNumber = $objResponse->shipmentTrackingNumber;
        $this->documento = $objResponse->documents;


        Log::debug(__CLASS__." ".__FUNCTION__." ".__LINE__);
       
    }

    /**
     * Rastreo busca los estatus con el LTD.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    public function trackingByNumber( $id ){
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);

        $basic = sprintf("%s:%s", Config('ltd.dhl.api_key'), Config('ltd.dhl.secret') );
        $authorization = sprintf("Basic %s",base64_encode($basic));

        $headers = ['Authorization' => $authorization  
                    ,'Content-Type' => 'application/json'
                ];

        $uri = sprintf("%sshipments/%s/tracking",Config('ltd.dhl.base_uri'), $id );
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        Log::debug(print_r($uri,true));
        $client = new Client();

        $reponse = $client->request("GET", $uri, [
                    'headers'   => $headers
        
                ]);

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $contenido = json_decode($reponse->getBody()->getContents());

        $pesoDimension = array();
        $this->ultimaFecha = "1999-12-31 23:59:59";

        foreach ($contenido->shipments as $key => $value) {
            Log::info(print_r($value,true));
            $pesoDimension['largo'] = (isset($value->length)) ? $value->length : 0 ;
            $pesoDimension['ancho'] = (isset($value->width)) ? $value->width : 0 ;
            $pesoDimension['alto'] = (isset($value->high)) ? $value->high : 0 ;
            $pesoDimension['peso'] = $value->totalWeight;

            if ( count($value->events) ) {
                Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
                
                foreach ($value->events as $keyI => $evento) {
                    switch ($evento->typeCode) {
                        case 'PU':
                            $this->pickupFecha = sprintf("%s %s", $evento->date,$evento->time);  
                            $this->latestStatusDetail = $evento->typeCode;
                            break;

                        case 'RT':
                            $this->quienRecibio = "DEVUELTO";
                            $this->latestStatusDetail = $evento->typeCode;
                            break;
                        
                        case 'OK':
                            $this->ultimaFecha = sprintf("%s %s", $evento->date,$evento->time);
                            $this->latestStatusDetail = $evento->typeCode;
                            $this->quienRecibio = isset($evento->signedBy) ? $evento->signedBy : "No Documentado"  ;
                            break;
                        default:
                            $this->latestStatusDetail = $evento->typeCode;
                            $this->ultimaFecha = sprintf("%s %s", $evento->date,$evento->time);
                            break;
                    }
                }
                    

                $this->exiteSeguimiento = true;

            } else {
                Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
                $this->exiteSeguimiento = false;
            }

        }
        $this->paquete = $pesoDimension;

        
    }



    public function getTrackingNumber(){
        return $this->trackingNumber;
    }

    public function getDocumento(){
        return $this->documento;
    }

    public function getExiteSeguimiento(){
        return $this->exiteSeguimiento;
    }

    public function getPaquete(){
        return $this->paquete;
    }

    public function getUltimaFecha(){
        return $this->ultimaFecha;
    }

    public function getQuienRecibio(){
        return $this->quienRecibio;
    }

    public function getPickupFecha(){
        return $this->pickupFecha;
    }
    
    public function getLatestStatusDetail(){
        return $this->latestStatusDetail;
    }
}
