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
use App\Models\LtdCredencial;
use App\Models\EmpresaEmpresas;

class Estafeta {

    private static $instance;

    private $token;
    private $baseUri;
    private $keyId;
    private $secret;
    private $keyIdRastreo;
    private $secretRastreo;
    private $clientID;
    private $customerNumber;
    private $salesOrganization;

    public $documento = 0; 
    private $resultado = array();
    private $trackingNumber = "trackingNumber";
    private $exiteSeguimiento = false;
    private $quienRecibio = "--------";
    private $paquete = array();
    private $latestStatusDetail;
    private $ultimaFecha = "1999-12-31 23:59:59";
    private $pickupFecha;

    public function __construct( $empresa_id= 1, $plataforma = 'WEB',int $recursoId = 1){

        Log::info(__CLASS__." ".__FUNCTION__);
        $this->baseUri = Config('ltd.estafeta.base_uri');
        
        
        $this->credenciales( $empresa_id, $plataforma, $recursoId );

        $formParams = [
                'grant_type' => 'client_credentials',
                'client_id' => config('ltd.estafeta.api_key'),
                'client_secret' => config('ltd.estafeta.secret'),
                'scope' => env('ESTAFETA_SCOPE', 'https://graph.microsoft.com/.default'),
        ];

        $sesion = LtdSesion::where('ltd_id', Config('ltd.estafeta.id') )
                ->where('servicio',$recursoId)
                ->where('empresa_id',$empresa_id)
                ->where('expira_en','>', Carbon::now())
                ->first();

        if (!is_null($sesion)) {
            Log::info(__CLASS__." ".__FUNCTION__." Token existente");
            $this->token = $sesion->token;

        }else {
            Log::info(__CLASS__." ".__FUNCTION__." Seccion Else");
            
            $client = new Client(['base_uri' => Config('ltd.estafeta.token_uri') ]);
            $headers = ['Content-Type' => 'application/x-www-form-urlencoded'];

            Log::debug( Config('ltd.estafeta.token_uri') );
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." formParams");
            Log::info('ESTAFETA TOKEN REQUEST', [
                'grant_type' => $formParams['grant_type'],
                'scope_configured' => !empty($formParams['scope']),
                'client_id_configured' => !empty($formParams['client_id']),
                'client_secret_configured' => !empty($formParams['client_secret']),
            ]);

            $response = $client->request('POST', 'oauth2/v2.0/token',
                ['form_params' => $formParams
                , 'headers'     => $headers]
            );

            if ($response->getStatusCode() == "200") {
                Log::info(__CLASS__." ".__FUNCTION__."".__LINE__." StatusCode 200");

                $json = json_decode($response->getBody()->getContents());

                $this->token = $json->access_token;

                $expiresIn = isset($json->expires_in)
                    ? (int) $json->expires_in
                    : 3600;

                $insert = [
                    'empresa_id' => $empresa_id,
                    'ltd_id'     => Config('ltd.estafeta.id'),
                    'token'      => $this->token,
                    'servicio'   => $recursoId,
                    'expira_en'  => Carbon::now()->addSeconds($expiresIn - 300),
                ];

                Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." insert token");
                Log::info('ESTAFETA TOKEN CACHE', [
                    'empresa_id' => $empresa_id,
                    'servicio' => $recursoId,
                    'expira_en' => (string) $insert['expira_en'],
                ]);

                $id = LtdSesion::create($insert)->id;

                Log::info(__CLASS__." ".__FUNCTION__." ID LTD SESION $id");
            }
            
        }
        
    }

    /**
     * clienteRest busca crear una funcion donde se inicialice la peticion via guzzle.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return GuzzleHttp\Client $response
     */

    private function clienteRest(array $body,$metodo = 'GET', string $baseUri, $servicio, int $servicioID=1){
        Log::debug(__CLASS__." ".__FUNCTION__." INICIANDO-----------------");
        Log::debug($baseUri);
        $client = new Client(['base_uri' => $baseUri]);
        $authorization = sprintf("Bearer %s",$this->token);

        $apiKey = ($servicioID === 1) ? $this->keyId : $this->keyIdRastreo;
        $headers = ['Authorization' => $authorization
                    ,'Content-Type' => 'application/json'
                    ,'charset' => 'utf-8'
                    ,'apiKey'   => $apiKey
                ];

        Log::info('ESTAFETA HTTP HEADERS', [
            'header_names' => array_keys($headers),
        ]);
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." Body ");
        $bodyJson = json_encode($body);
        Log::info('ESTAFETA HTTP REQUEST', [
            'method' => $metodo,
            'service' => $servicio,
            'body_size' => strlen($bodyJson),
        ]);
        
        Log::debug(__CLASS__." ".__FUNCTION__." FINALIZANDO-----------------");
        return $client->request($metodo,$servicio , [
                    'headers'   => $headers
                    ,'body'     => $bodyJson
                ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  array  $body
     * @return \Illuminate\Http\Response
     */

    public function envio($body, $plataforma = "WEB", $formatoImpresion = "FILE_PDF")
{
    Log::info(__CLASS__ . " " . __FUNCTION__ . " " . __LINE__ . " INICIO ------------------");

    $client = new Client(['base_uri' => $this->baseUri]);
    $authorization = sprintf("Bearer %s", $this->token);

    $baseUri = rtrim($this->baseUri, '/');

    $uri = sprintf(
        "%s/labelrest/v1/wayBills?outputType=%s&outputGroup=REQUEST&responseMode=SYNC_INLINE&printingTemplate=NORMAL_TIPO7_ZEBRAORI",
        $baseUri,
        $formatoImpresion
    );

    $subscriptionKey = env('ESTAFETA_SUBSCRIPTION_KEY', env('ESTAFETA_APIKEY', $this->keyId));

    $headers = [
        'Authorization' => $authorization,
        'Content-Type'  => 'application/json',
        'Accept'        => 'application/json',

        // Azure API Management estándar
        'Ocp-Apim-Subscription-Key' => $subscriptionKey,

        // Compatibilidad con Estafeta si espera apikey
        'apikey' => $subscriptionKey,
        'apiKey' => $subscriptionKey,
    ];

    Log::info('ESTAFETA LABEL REQUEST', [
        'base_uri_configured' => !empty($this->baseUri),
        'subscription_key_configured' => !empty($subscriptionKey),
        'header_names' => array_keys($headers),
        'service_type' => data_get(
            $body,
            'labelDefinition.serviceConfiguration.serviceTypeId'
        ),
        'origin_zip' => data_get(
            $body,
            'labelDefinition.serviceConfiguration.originZipCodeForRouting'
        ),
        'destination_zip' => data_get(
            $body,
            'labelDefinition.location.destination.homeAddress.address.zipCode'
        ),
        'quantity' => data_get(
            $body,
            'labelDefinition.serviceConfiguration.quantityOfLabels'
        ),
        'output_type' => $formatoImpresion,
    ]);

    $response = $client->request('POST', $uri, [
        'headers' => $headers,
        'body'    => json_encode($body),
    ]);

    $this->resultado = json_decode($response->getBody()->getContents());

    Log::info(__CLASS__ . " " . __FUNCTION__ . " " . __LINE__);

    $this->documento = $this->resultado->data;
    Log::info('ESTAFETA LABEL DOCUMENT', [
        'base64_size' => is_string($this->documento)
            ? strlen($this->documento)
            : 0,
    ]);

    $this->trackingNumber = $this->resultado->labelPetitionResult->result->description;

    Log::info(__CLASS__ . " " . __FUNCTION__ . " FIN ------------------");
}


    /**
     * Store a newly created resource in storage.
     *
     * @param  array  $body
     * @return \Illuminate\Http\Response
     */

    public function rastreo($trackingNumber = 1,)
    {
        Log::info(__CLASS__." ".__FUNCTION__." INICIO ------------------");

        $pesoDimension  = array('peso' => 0
                    , 'largo' => 0
                    , 'ancho' => 0
                    , 'alto' => 0
                );


        $body = array (
          'suscriberId' => $this->clientID,
          'login' => $this->user,
          'password' => $this->passwd,
          'searchType' => array (
            'type' => 'L',
            'waybillList' => array (
                'waybillType' => 'G',
                'waybills' => array (
                    'string' => array (
                        0 => $trackingNumber,
                    ),
                ),
            ),
          ),
          'searchConfiguration' => array (
            'historyConfiguration' => array (
              'historyType' => 'all',
              'includeHistory' => true,
            ),
            'includeCustomerInfo' => true,
            'includeDimensions' => true,
            'includeInternationalData' => true,
            'includeMultipleServiceData' => true,
            'includeReturnDocumentData' => true,
            'includeSignature' => true,
            'includeWaybillReplaceData' => true,
          ),
        );

        Log::debug(json_encode($body));

        $response = $this->clienteRest($body, 'POST',Config('ltd.estafeta.rastreo.base_uri'),Config('ltd.estafeta.rastreo.servicio'), 2);

    
        $tmp = $response->getBody()->getContents();
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." Response");
        Log::debug(print_r($tmp,true));
        $contenido = json_decode($tmp);
        $response = $contenido->ExecuteQueryResponse->ExecuteQueryResult->trackingData;
        
        if (isset($response->TrackingData)) {
            Log::info("Existe tracking");

            $trackingData = $response->TrackingData;
            
            Log::info(__CLASS__." ".__FUNCTION__." Ultimo estatus");
            $this->latestStatusDetail = $trackingData->statusENG;
            Log::debug(print_r($this->latestStatusDetail,true));
            if (isset($trackingData->dimensions->weight)) {
                $weight = $trackingData->dimensions->weight;
                $volumetricWeight = $trackingData->dimensions->volumetricWeight;

                $pesoDimension['largo'] = $trackingData->dimensions->length;
                $pesoDimension['ancho'] = $trackingData->dimensions->width;
                $pesoDimension['alto'] = $trackingData->dimensions->height;
                $pesoDimension['peso'] = ( $weight > $volumetricWeight) ? $weight : $volumetricWeight;
            }
            
            $this->paquete = $pesoDimension;

            $receiverName = explode(":", $trackingData->deliveryData->receiverName);
            $this->quienRecibio = ( count($receiverName) === 2) ? $receiverName[1] : "No entregado aun" ;

            Log::info(__CLASS__." ".__FUNCTION__." Ultimo estatus");
            if ($this->latestStatusDetail === "DELIVERED") {
                $ultimaFecha = $trackingData->deliveryData->deliveryDateTime;
            }else{
                $ultimaFecha = Carbon::now();
                if ( isset($trackingData->history->History)) {
                    if (is_array($trackingData->history->History)) {
                        $evento = count($trackingData->history->History)-1;
                        $ultimoEvento = $trackingData->history->History[$evento];
                        $ultimaFecha = $ultimoEvento->eventDateTime;
                    } else {
                        $ultimaFecha = $trackingData->history->History->eventDateTime;
                    }
                }
                
            }
            $this->ultimaFecha = Carbon::parse($ultimaFecha)->format('Y-m-d H:i:s');
            
            Log::debug(print_r($this->ultimaFecha,true));
            Log::debug(__CLASS__." ".__FUNCTION__." ".__LINE__." pickupFecha");
            Log::debug(print_r($trackingData->pickupData,true));
            $this->pickupFecha = Carbon::parse($trackingData->pickupData->pickupDateTime)->format('Y-m-d H:i:s');

            $this->exiteSeguimiento = true;
            $this->resultado = $response;
        }else{
            Log::debug("Sin tracking");
            $this->exiteSeguimiento = false;   
        }

        Log::info(__CLASS__." ".__FUNCTION__." FIN ------------------");
    }

    public static function getInstance( $empresaId = 1,$plataforma = "WEB", $servicioID=1 ){
        if (!self::$instance) {
            Log::debug(__CLASS__." ".__FUNCTION__." Creando intancia");
            self::$instance = new self($empresaId, $plataforma, $servicioID);
        }
        Log::debug(__CLASS__." ".__FUNCTION__." return intancia");
        return self::$instance;
    }

    /**
     * Se busca obtener credenciales y datos sencibles de Estafeta basado en Clientes Globales.
     *
     * @param  
     * @return 
     */

    private function credenciales($empresa_id, $plataforma="WEB", $recursoId=1){
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." INICIO");
        

        $empresas = EmpresaEmpresas::where('empresa_id',$empresa_id)->pluck('id')->toArray();
        
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." EmpresaEmpresas");
        Log::debug(print_r($empresas,true));

        $ltdCredencial = LtdCredencial::where('ltd_id',2)
                                ->whereIn('empresa_id',$empresas);

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." LtdCredencial");
        Log::info('ESTAFETA CREDENTIAL SEARCH', [
            'empresa_count' => count($empresas),
            'resource_id' => $recursoId,
        ]);
                     
        if ($recursoId === 1) {
            Log::info(__CLASS__." ".__FUNCTION__." Token para etiquetas");
            $credenciales = $ltdCredencial->where('recurso','LABEL')->get()->toArray();

            if ( count($credenciales) < 1)
                throw ValidationException::withMessages(['No exiten credenciales para el LTD, Valida con tu proveedor']);
            
        } else {
            Log::info(__CLASS__." ".__FUNCTION__." Token para rastreo");
            $credenciales = $ltdCredencial->where('recurso',"TRACKING")->get()->toArray();

            if ( count($credenciales) < 1)
                throw ValidationException::withMessages(['No exiten credenciales para el LTD, Valida con tu proveedor']);

            $this->keyIdRastreo = $credenciales[0]['key_id'];
            $this->secretRastreo = $credenciales[0]['secret'];
            
        }

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." asignar credenciales");
        Log::info('ESTAFETA CREDENTIALS LOADED', [
            'resource_id' => $recursoId,
            'credentials_found' => count($credenciales),
            'key_configured' => !empty($credenciales[0]['key_id'] ?? null),
            'secret_configured' => !empty($credenciales[0]['secret'] ?? null),
        ]);
        $this->keyId = $credenciales[0]['key_id'];
        $this->secret = $credenciales[0]['secret'];
        $this->clientID = $credenciales[0]['client_id'];
        $this->customerNumber = $credenciales[0]['customer_number'];
        $this->salesOrganization = $credenciales[0]['organization'];
        $this->user = $credenciales[0]['user'];
        $this->passwd = $credenciales[0]['passwd'];



        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." FINAL");
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

    public function getExiteSeguimiento(){
        return $this->exiteSeguimiento;
    }

    public function getQuienRecibio(){
        return $this->quienRecibio;
    }

    public function getPaquete(){
        return $this->paquete;
    }

    public function getLatestStatusDetail(){
        return $this->latestStatusDetail;
    }

    public function getUltimaFecha(){
        return $this->ultimaFecha;
    }

    public function getPickupFecha(){
        return $this->pickupFecha;
    }

    public function getClientID(){
        return $this->clientID;
    }

    public function getCustomerNumber(){
        return $this->customerNumber;
    }

    public function getSalesOrganization()
    {
        return $this->salesOrganization;
    }
}

?>
