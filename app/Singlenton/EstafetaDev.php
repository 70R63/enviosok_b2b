<?php

namespace App\Singlenton;

use GuzzleHttp\Client;
use Log;
use Carbon\Carbon;
use Config;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Http;

#CLASES DE NEGOCIO 
use App\Models\LtdSesion;




class EstafetaDev {

    private static $instance;

    private $token;
    private $baseUri;

    public $documento = 0; 
    private $resultado = array();
    private $trackingNumber = "trackingNumber";

    public function __construct(int $ltd_id, $empresa_id= 1, $plataforma = 'WEB'){

        Log::info(__CLASS__." ".__FUNCTION__);
        $this->baseUri = 'https://labelqa.estafeta.com/';
        
        if ($plataforma == 'WEB'){
            $empresa_id = auth()->user()->empresa_id;
        } 
        
        $sesion = LtdSesion::where('ltd_id', $ltd_id)
                ->where('empresa_id',$empresa_id)
                ->where('expira_en','>', Carbon::now())
                ->where('ambiente',"DEV")
                ->first();

        if (!is_null($sesion)) {
            $this->token = $sesion->token;

        }else {
            Log::info(__CLASS__." ".__FUNCTION__." Seccion Else");
            $client = new Client(['base_uri' => 'https://apiqa.estafeta.com:8443/']);
            $headers = ['Content-Type' => 'application/x-www-form-urlencoded'];

            $formParams = [
                'client_id' => 'l76a4958a420d244328a2daa8d68740c75',
                'client_secret' => '1daa8683d556479698c79d46004ef490',
                'grant_type' => 'client_credentials'
                ,'scope' => 'execute'
            ];

            $response = $client->request('POST', 'auth/oauth/v2/token',
                ['form_params' => $formParams
                , 'headers'     => $headers]
            );

            if ($response->getStatusCode() == "200"){
                $json = json_decode($response->getBody()->getContents());

                $this->token = $json->access_token;

                $insert = array('empresa_id' => $empresa_id
                    ,'ltd_id'   => $ltd_id
                    ,'token'    => $this->token
                    ,'expira_en'=> Carbon::now()->addMinutes(1380)
                    ,'ambiente' => "DEV"
                     );

                $id = LtdSesion::create($insert)->id;
                Log::info(__CLASS__." ".__FUNCTION__." ID LTD SESION $id");
            }
            
        }
        
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  array  $body
     * @return \Illuminate\Http\Response
     */

    public function envio($body){
        Log::info(__CLASS__." ".__FUNCTION__." INICIO ------------------");
        
        $client = new Client(['base_uri' => $this->baseUri]);
        $authorization = sprintf("Bearer %s",$this->token);

        $headers = [
            'Authorization' => $authorization
            ,'Content-Type' => 'application/json'
            ,'Accept'    => 'application/json'
            ,'apiKey'   => 'l76a4958a420d244328a2daa8d68740c75'
        ];
        
        Log::debug(print_r("Armando Peticion",true));

        $response = $client->request('POST', 'v1/wayBills?outputType=FILE_PDF&outputGroup=REQUEST&responseMode=SYNC_INLINE&printingTemplate=NORMAL_TIPO7_ZEBRAORI', [
            'headers'   => $headers
            ,'body'     => json_encode($body)
        ]);

        $this -> resultado = json_decode($response->getBody()->getContents());

        $this->documento = $this->resultado->data;
        $this->trackingNumber = $this->resultado->labelPetitionResult->result->description;
        Log::info(__CLASS__." ".__FUNCTION__." FIN ------------------");
    }

    public static function getInstance( int $ltd_id){
        if (!self::$instance) {
            Log::debug(__CLASS__." ".__FUNCTION__." Creando intancia");
            self::$instance = new self($ltd_id);
        }
        Log::debug(__CLASS__." ".__FUNCTION__." return intancia");
        return self::$instance;
    }

    public function setToken($value){
        $this->token = $value;
    }

    public function getToken(){
        return $this->token;
    }

    public function getResultado(){
        return $this->resultado;
    }

    public function getTrackingNumber(){
        return $this->trackingNumber;
    }
}

?>