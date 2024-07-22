<?php

namespace App\Negocio\Guias;

use Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

//MODELS
use App\Models\Guia;
use App\Models\GuiasPaquete;
use App\Models\Cliente;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\TarifasMostrador ;
use App\Models\LtdCobertura;
use App\Models\Tarifas;
use App\Models\Servicio;

//DTOS
use App\Dto\FedexDTO;
use App\Dto\Guia as GuiaDTO;

// singlenton
use App\Singlenton\Fedex as sFedex;

//Negocio
use App\Negocio\Guias\Cotizacion as nCotizacion;
use App\Negocio\Fedex_tarifas as nFedexTarifas;
use App\Negocio\Saldos\Saldos as nSaldos;

class Creacion {

	private $insert = array();
	private $notices = array();
	private $namePdf = array();
    private $response = array();
    private $zona = "";
    private $sucursalJson = array();
    private $clienteJson = array();
    private $precio = 0.00;
    private $paquete;

	/**
     * Se obtienen los datos para armar el insert de fedex
     * 
     * @param array $parametros
     * @return void
     */

    public function fedex($data, $canal ){
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $fedexDTO = new FedexDTO();
        //Log::debug(print_r($data->all(),true));
        $etiqueta = $fedexDTO->parser($data);

        $this->fedex = sFedex::getInstance(Config('ltd.fedex.id'), 2, "WEB", "PRD" );
        $this->fedex->envio( json_encode($etiqueta, JSON_UNESCAPED_UNICODE));

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $guiaDTO = new GuiaDTO();


        $guiaDTO->parseoFedex($data, $canal);
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $this->insert = $guiaDTO->getInsert();

    }

    /**
     * Se obtienen los datos para armar el insert de fedex
     * 
     * @param array $parametros
     * @return void
     */
    public function recurenciaPorDocumento($data, $numeroDeSolicitud, $canal="API"){
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $boolPrecio = true;
        $i=1;
        
        $this->notices = array("Número de Solicitud: $numeroDeSolicitud ");
        foreach ($this->fedex->getDocumentos() as $key => $documento) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            
            $this->insert['tracking_number'] = $documento->trackingNumber;
            $this->insert['documento'] = $documento->packageDocuments[0]->url;
            $this->insert['numero_solicitud'] = $numeroDeSolicitud;
            Log::debug(print_r($this->insert ,true));

            $carbon = Carbon::now();
            $unique = md5( (string)$carbon);
            $carbon->settings(['toStringFormat' => 'Y-m-d-H-i-s.u']);
            $this->namePdf = sprintf("%s-%s-%s.pdf",(string)$carbon,$this->insert['empresa_id'],$unique);

            Storage::disk('public')->put($this->namePdf, file_get_contents($this->insert['documento']));


            if ($i > 1) {
                Log::debug(__CLASS__." ".__FUNCTION__." ".__LINE__." Limpiar costos");
                $this->insert = nGuia::costosEnCero( $this->insert );
            }   
            
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." Guia::create");
            //$this->insert['canal'] = $canal;
            $id = Guia::create($this->insert)->id;
            $this->notices[] = sprintf("El registro de la solicitud se genero con exito con el ID %s ", $id);


            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            $guiaPaqueteInsert = GuiaDTO::validaPiezasPaquete($data, $key, $boolPrecio, $id);
            $boolPrecio = false;

            $idGuiaPaquite = GuiasPaquete::create($guiaPaqueteInsert)->id;
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." idGuiaPaquite =$idGuiaPaquite");
            $i++;
        }

    }

    /**
     * Se obtienen los datos para armar el insert de fedex
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
     * @throws \LogicException
     *
     * @param array $parametros eseseses
     * 
     * @var int 
     * @var App\Negocio\Fedex_tarifas $fedexTarifa
     * @var string $cp 
     * @var string $cp_d
     * @var array $body valores unicos par envio al LTD
     * @var string $canal valor que indentifica de donde se realiza la peticion
     * 
     * 
     * @return json Objeto con la respuesta de exito o fallo 
     */

    public function fedexApi($data, $ambiente="PRD"){
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            
        $canal = "API";
        $data['numeroDeSolicitud'] = Carbon::now()->timestamp;

        $data['accountNumber']['value'] = config('ltd.fedex.cred.accountNumber');

        $labelSpecification = ['imageType' => 'PDF'
                            , 'labelStockType'=> 'PAPER_85X11_TOP_HALF_LABEL'
                            ];
        $data['requestedShipment']['labelSpecification'] = $labelSpecification;

        $data['requestedShipment']['packagingType'] = 'YOUR_PACKAGING';
        $data['requestedShipment']['pickupType'] = 'USE_SCHEDULED_PICKUP';

        $data['requestedShipment']['blockInsightVisibility'] = false;

        $paymentType = ['paymentType' => 'SENDER'];
        $data['requestedShipment']['shippingChargesPayment'] = $paymentType;
        $this->sucursalJson = $data['requestedShipment']['shipper'];
        $this->clienteJson = $data['requestedShipment']['recipients'][0];
        $this->paquete = $data['requestedShipment']['requestedPackageLineItems'][0];

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);

        $cp = $this->sucursalJson['address']['postalCode'];
        $cp_d = $this->clienteJson['address']['postalCode'];
        $data['cp'] = $cp;
        $data['cp_d'] = $cp_d;

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);

        $user = User::where("id",$data['user_id'])
            ->get()
            ->toArray()[0];

        $data['empresa_id'] = $user['empresa_id'];
        $data['name'] = $user['name'];
        $data['esManual'] = "API";
        $data['ltd_id'] = "1";
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $data = $this->validaCliente($data);

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $data = $this->validaSucursal($data);

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $data = $this->cotizacion($data);
            
        $this->saldo($data);
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $this->fedex = sFedex::getInstance(config('ltd.fedex.id'), $data['empresa_id'], "API", $ambiente);
        $this->fedex->envio( json_encode($data, JSON_UNESCAPED_UNICODE));

        $documentos = $this->fedex->getDocumentos();

        foreach ($documentos as $key => $value) {
            $data['documento'] = $value->packageDocuments[0]->url;
            $data['tracking_number'] = $value->trackingNumber;
            unset($value->netRateAmount);
            unset($value->baseRateAmount);
            Log::debug( print_r($data['documento'],true) );

            if ($ambiente === "PRD") {
                $this->insertDTO($data, $canal);
                Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
                $id = Guia::create($this->insert)->id;
                $this->notices[]= sprintf("El registro de la solicitud se genero con exito con el ID %s ", $id);
                
            } else {
                $this->notices[]= sprintf("El registro de la solicitud se genero con exito con el ID xxxx");
            }
            
        }
    
        
        $this->responseCustom();
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
    }

     /**
     * Se genera un Response custom para quitar valores interno de  arespuesta de FEDEX
     * 
     * @author Javier Hernandez
     * @copyright 2022-2023 XpertaMexico
     * @package App\Negocio\Guias
     * @api
     * 
     * @version 1.0.0
     * 
     * @since 1.0.0 Primera version de la funcion responseCustom
     * 
     * @throws
     *
     * @param 
     * 
     * @var array responseCustom 
     * 
     * 
     * @return $data Se agra informacion segun la necesidad
     */

    public function responseCustom(){
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);

        $responseCustom =$this->fedex->getResponse();
        unset($responseCustom->output->transactionShipments[0]->completedShipmentDetail->shipmentRating);
        
        $this->response=$responseCustom;
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
    }

    /**
     * Valida la existencia del cliente
     * 
     * @author Javier Hernandez
     * @copyright 2022-2023 XpertaMexico
     * @package App\Negocio\Guias
     * @api
     * 
     * @version 1.0.0
     * 
     * @since 1.0.0 Primera version de la funcion validaCliente
     * 
     * @throws
     *
     * @param array $data Informacion general de la petricion
     * 
     * @var int 
     * 
     * 
     * @return $data Se agra informacion segun la necesidad
     */

    public function validaCliente($data){
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
         

        $data['nombre_d']= trim($this->clienteJson['contact']['personName']);

        $direcciones = $this->clienteJson['address']['streetLines'];

        $direccion = $direcciones[0];
       
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $cliente = new Cliente();
        $cliente->validaCliente($data);

        if ( !$cliente->getExiste() ) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." Cliente no existe");

            $data['contacto_d'] = $data['nombre_d'];
            $data['direccion_d']= $direccion;
            $data['direccion2_d']="";
            $data['colonia_d']  = $this->clienteJson['address']['city'];
            $data['ciudad_d']   = $this->clienteJson['address']['city'];
            $data['entidad_federativa_d']=$this->clienteJson['address']['stateOrProvinceCode'];
            $data['celular_d']  = $this->clienteJson['contact']['phoneNumber'];
            $data['telefono_d'] = $this->clienteJson['contact']['phoneNumber'];;
            $data['no_ext_d']   = '';
            $data['no_int_d']   = '';
    
            $cliente->insertSemiManual($data);

        }
        $data['cliente_id']=$cliente->getId();

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        return $data;
        
    }

    /**
     * Valida la existencia del remitente (sucursal)
     * 
     * @author Javier Hernandez
     * @copyright 2022-2023 XpertaMexico
     * @package App\Negocio\Guias
     * @api
     * 
     * @version 1.0.0
     * 
     * @since 1.0.0 Primera version de la funcion validaSucursal
     * 
     * @throws
     *
     * @param array $data Informacion general de la petricion
     * 
     * @var int 
     * 
     * 
     * @return $data Se agra informacion segun la necesidad
     */

    public function validaSucursal($data){
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);

        $sucursalJson = $this->sucursalJson;

        $data['nombre']= trim($sucursalJson['contact']['personName']);
        $data['contacto'] = $data['nombre'];
        $direcciones = $this->sucursalJson['address']['streetLines'];

        $direccion = $direcciones[0];

        $remitente = new Sucursal();
        $remitente->existe($data);
        
        if ( !$remitente->getExiste() ) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);

            $data['direccion']= $direccion;
            $data['direccion2']="";
            $data['colonia']  = $this->sucursalJson['address']['city'];
            $data['ciudad']   = $this->sucursalJson['address']['city'];
            $data['entidad_federativa']= $this->sucursalJson['address']['stateOrProvinceCode'] ;
            $data['celular']  = $sucursalJson['contact']['phoneNumber'];
            $data['telefono'] = $sucursalJson['contact']['phoneNumber'];
            $data['no_ext']   = "";
            $data['no_int']   = "";

            $remitente->insertParse($data);
        }
        $data['sucursal_id']=$remitente->getId();
        $data['sucursal'] = $data['sucursal_id'];

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);

        return $data;
    }


    /**
     * Valida la cotizacion 
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
     * @param array $data Informacion general de la peticion
     * 
     * @var $cotizacion Se usa para generar peticion para validar las cotizaciones
     * @var $dimensiones  
     * 
     * 
     * @return $data Se agra informacion segun la necesidad
     */

    private function cotizacion($data){
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);

        $nFedexTarifa = new nFedexTarifas();
        $nFedexTarifa->zonas($data['cp'], $data['cp_d']);
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        
        $zona = $nFedexTarifa->getZona();
        Log::debug(print_r($zona, true));

        if ( count($zona) < 1)
            throw ValidationException::withMessages(array("No existen zonas, Validar con tu administrador"));

        $data['zona']=$zona[0];


        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $data = $this->validaLtdCobertura($data);   
        
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $data = $this->dimensiones($data);

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $data = $this->precioDescuentoPorEmpresa($data);


        $serguroCostoPorcentaje=2; 
        $data['extendida']= 0;
          
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        if ($this->paquete['declaredValue']['amount'] <= 0 ) {
            $data['valor_envio']= 0;
            $data['costo_seguro']=0;
            $data['bSeguro'] = false;
        } else {
            $data['valor_envio']= $this->paquete['declaredValue']['amount'];
            $subCostoSeguro = ($data['valor_envio']*$serguroCostoPorcentaje)/100;
            $data['costo_seguro'] = ($subCostoSeguro);
            $data['bSeguro'] = true;
        }
        
        
        $data['costo_kg_extra']=0;
        $data['sobre_peso_kg']=0;

        //caclulo extendida 
        $data['costo_extendida'] = 0;
        if ($data['extendida'] === 'SI') {
            $data['costo_extendida']=(170);
        }
        

        $data['peso_bascula'] = $data['peso'];
        $data['peso_dimensional'] = ($data['alto']*$data['ancho']*$data['largo'])/5000;
        

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $data['peso_facturado'] = ($data['peso_bascula'] > $data['peso_dimensional']) ? ceil($data['peso_bascula']) : ceil($data['peso_dimensional']) ;
        
        $data['pesoFacturado']=$data['peso_facturado'];

        
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $data['subPrecio'] = $data['costo_base']+$data['costo_kg_extra']+$data['costo_seguro']+$data['costo_extendida'];

        $data['piezas']=$this->paquete['groupPackageCount']; //groupPackageCount

        $data['precio']=$data['subPrecio']*1.16;// suma valores adiocnales

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        return $data;
    }


    /**
     * Valida los datos para obtener el precio de una guia
     * 
     * @author Javier Hernandez
     * @copyright 2022-2023 XpertaMexico
     * @package App\Negocio\Guias
     * @api
     * 
     * @version 1.0.0
     * 
     * @since 1.0.0 Primera version de la funcion precioDescuentoPorEmpresa
     * 
     * @throws
     *
     * @param array $data Informacion general de la peticion
     * 
     * @var $cotizacion Se usa para generar peticion para validar las cotizaciones
     * @var float $precio 
     * 
     * 
     * @return $data Se agra informacion segun la necesidad
     */

    public function precioDescuentoPorEmpresa($data){
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        
        $kg = $this->paquete['weight']['value'];


        $tarifaMostradorQuery = TarifasMostrador::where('zona',$data['zona'])
                    ->where('ltd_id', $data['ltd_id'])
                    ->where('kg', $kg)
                    
                    //->getBindings()
                    ;
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        if ( isset($data['servicio_id']) ){
            $tarifaMostradorQuery = $tarifaMostradorQuery->where('servicio_id', $data['servicio_id']);
        }

        $tarifaMostrador = $tarifaMostradorQuery->get()->toArray();
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        Log::debug( print_r($tarifaMostrador,true));

        if (count($tarifaMostrador) <1)
            throw new \LogicException("No Exite Tarifa");

        $descuentoPorcentaje = 43;
        $fsc = 17;

        $descuento = ($tarifaMostrador[0]['precio']*$descuentoPorcentaje)/100;

        $precioConDescuento = $tarifaMostrador[0]['precio']-$descuento;
        $fscCargo = ($precioConDescuento*$fsc)/100;

        $subTotalMostrador = $precioConDescuento+$fscCargo;
        $data['costo_base']= $subTotalMostrador;
        
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        Log::debug( print_r($subTotalMostrador,true));

        return $data;

    }


    /**
     * Valida la existencia del remitente (sucursal)
     * 
     * @author Javier Hernandez
     * @copyright 2022-2023 XpertaMexico
     * @package App\Negocio\Guias
     * 
     * @version 1.0.0
     * 
     * @since 1.0.0 Primera version de la funcion saldo
     * 
     * @throws
     *
     * @param array $data Informacion general de la peticion
     * 
     * @var int 
     * @var float $monto
     * 
     * 
     * @return $data Se agra informacion segun la necesidad
     */

    public function saldo($data){
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $saldo = new nSaldos();
        $monto = $saldo-> porEmpresa($data['empresa_id']);

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $saldo->validaSaldo($monto);

        $saldo->menosPrecio($data["sucursal_id"], $data["precio"]);
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
    }


    /**
     * Valida la dimensiones y parsea $data 
     * 
     * @author Javier Hernandez
     * @copyright 2022-2023 XpertaMexico
     * @package App\Negocio\Guias
     * @api
     * 
     * @version 1.0.0
     * 
     * @since 1.0.0 Primera version de la funcion dimensiones
     * 
     * @throws
     *
     * @param array $data Informacion general de la peticion
     * 
     * @var $cotizacion Se usa para generar peticion para validar las cotizaciones
     * @var array $dimensiones Contiene los valors de alto, ancho y largo 
     * 
     * 
     * @return $data Se agra informacion segun la necesidad
     */

    public function dimensiones($data){
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);

        $data['peso'] = $this->paquete['weight']['value'];   

        $dimensiones = $this->paquete['dimensiones'];

        foreach ($this->paquete['dimensiones'] as $key => $value) {
            $data[$key] = $value;
        }
        
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);

        return $data;
    
    }


    /**
     * Parsea valores para insert de una guia 
     * 
     * @author Javier Hernandez
     * @copyright 2022-2023 XpertaMexico
     * @package App\Negocio\Guias
     * @api
     * 
     * @version 1.0.0
     * 
     * @since 1.0.0 Primera version de la funcion insertDTO
     * 
     * @throws
     *
     * @param array $data Informacion general de la peticion
     * 
     * @var $cotizacion Se usa para generar peticion para validar las cotizaciones
     * 
     * 
     * @return $data Se agra informacion segun la necesidad
     */

    public function insertDTO($data, $canal){
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);

        $guiaDTO = new GuiaDTO();
        $guiaDTO->parseoFedex($data, $canal);
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $this->insert = $guiaDTO->getInsert();
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);

    }


    /**
     * SE valida si el CP y PAquereia tiene Cobertura Extendida 
     * 
     * @author Javier Hernandez
     * @copyright 2022-2023 XpertaMexico
     * @package App\Negocio\Guias
     * @api
     * 
     * @version 1.0.0
     * 
     * @since 1.0.0 Primera version de la funcion insertDTO
     * 
     * @throws
     *
     * @param array $data Informacion general de la peticion
     * 
     * @var $ltdCobertura Array que tendra los datos de la cobertura
     * 
     * 
     * @return $data Se agra informacion segun la necesidad
     */

    public function validaLtdCobertura($data){
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $ltdCobertura = LtdCobertura::where("ltd_id", $data['ltd_id'])
                    ->where("cp",$data['cp_d'])
                    ->get()->toArray();

        Log::debug( print_r($ltdCobertura,true) );

        if ( ! (count($ltdCobertura) ===1) ) {
            throw ValidationException::withMessages(array("Inconsistencia en la Cobertura, Favor de validar con el Administrador"));
        }
        $data['extendida']=$ltdCobertura[0]['extendida'];
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);

        return $data;
    }
    


    /**
     * Valida la cotizacion 
     * 
     * @author Javier Hernandez
     * @copyright 2022-2023 XpertaMexico
     * @package App\Negocio\Guias
     * @api
     * 
     * @version 1.0.0
     * 
     * @since 1.0.0 Primera version de la funcion cotizadorPorServicio
     * 
     * @throws
     *
     * @param array $data Informacion general de la peticion
     * 
     * @var $cotizacion Se usa para generar peticion para validar las cotizaciones
     * @var $dimensiones  
     * 
     * 
     * @return $data Se agra informacion segun la necesidad
     */

    public function cotizadorPorServicio($data, $servicio){
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);

        $this->paquete= $data['requestedPackageLineItems'][0];
        $this->paquete['groupPackageCount'] = 0;
        $response = array();
        
        switch ($servicio) {
            case 'todos':
                Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);

                $servicios = array("1","2");
                foreach ($servicios as $key => $value) {
                    $data['servicio_id']=$value;
                    $response[] = $this->cotizacion($data);
                }
                break;
            
            case 'terrestre':
                Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
                $data['servicio_id']=1;
                $response[] = $this->cotizacion($data);
                break;
            case 'diasig':
                Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
                $data['servicio_id']=2;
                $response[] = $this->cotizacion($data);
                break;

            default:
                throw ValidationException::withMessages(array("Servicio no contrado o erroneo, favor de validar con tu administrador"));
                break;
        }

       
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);       
        foreach ($response as $key => $value) {
            Log::debug( print_r($value,true) );   
            unset($value['token']);
            unset($value['requestedPackageLineItems']);
            unset($value['user_id']);
            unset($value['ltd_id']);
            unset($value['pesoFacturado']);
            unset($value['piezas']);

            $mServicio = Servicio::where("id",$data['servicio_id'])
                ->firstOrFail();

            Log::debug($mServicio->nombre);
            unset($value['servicio_id']);
            $value['servicio_nombre'] = $mServicio->nombre;
            
            $this->response[]= $value;
        }


        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);

    }


    /**
     * Valida la cotizacion de Fedex
     * 
     * @author Javier Hernandez
     * @copyright 2022-2023 XpertaMexico
     * @package App\Negocio\Guias
     * @api
     * 
     * @version 1.0.0
     * 
     * @since 1.0.0 Primera version de la funcion soloCotizacion
     * 
     * @throws
     *
     * @param array $data Informacion general de la peticion
     * 
     * @var $cotizacion Se usa para generar peticion para validar las cotizaciones
     * @var $dimensiones  
     * 
     * 
     * @return $data Se agra informacion segun la necesidad
     */

    public function soloCotizacion($data, $servicio){
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);

        $this->paquete['weight']['value']= $data['peso'];
        $this->paquete['dimensiones']['alto'] =  $data['alto'];
        $this->paquete['dimensiones']['ancho'] =  $data['ancho'];
        $this->paquete['dimensiones']['largo'] =  $data['largo'];
        $this->paquete['declaredValue']['amount']= $data['valor_declarado'];

        $this->paquete['groupPackageCount'] = 1;
        $response = array();
        
        switch ($servicio) {
            
            case 'terrestre':
                Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
                $data['servicio_id']=1;
                $response[] = $this->cotizacion($data);
                break;
            case 'diasig':
                Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
                $data['servicio_id']=2;
                $response[] = $this->cotizacion($data);
                break;

            default:
                throw ValidationException::withMessages(array("Servicio no contrado o erroneo, favor de validar con tu administrador"));
                break;
        }

       
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);       
        foreach ($response as $key => $value) {
            Log::debug( print_r($value,true) );   
            
            $mServicio = Servicio::where("id",$data['servicio_id'])
                ->firstOrFail();

            Log::debug($mServicio->nombre);
            unset($value['servicio_id']);
            $value['servicio_nombre'] = $mServicio->nombre;

            $this->response= $this->resumenCotizacion($value);

            $this->notices[] ="Exito";
            $this->notices[]= sprintf("La cotizacion puede cambiar al momento de creacar de la guia");
        }


        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);

    }

    /**
     * Regresa la cotizacon de la solicutd de la guia
     * 
     * @author Javier Hernandez
     * @copyright 2022-2023 XpertaMexico
     * @package App\Negocio\Guias
     * 
     * @version 1.0.0
     * 
     * @since 1.0.0 Primera version de la funcion resumenCotizacion
     * 
     * @throws
     *
     * @param array $data Informacion general de la peticion
     * 
     * @var array $resumenCotizacion
     * @var array $resumen
     * 
     * 
     * @return $data Se agra informacion segun la necesidad
     */

    private function resumenCotizacion($data){
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $resumenCotizacion['costo'] = $data['costo_base'];
        $resumenCotizacion['kgs_extras'] = $data['sobre_peso_kg'];
        $resumenCotizacion['costo_kgs_extras'] = $data['costo_kg_extra'];
        $resumenCotizacion['valor_declarado'] = $data['valor_declarado'];
        $resumenCotizacion['costo_seguro'] = $data['costo_seguro'];
        $resumenCotizacion['costo_ae'] = $data['costo_extendida'];
        $resumenCotizacion['sub_total'] = $data['subPrecio'];
        $resumenCotizacion['total'] = $data['precio'];

        $resumen[] = $resumenCotizacion;
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        return $resumen;
        
    }

    public function zona($data){
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        return $data;
    }


    public function getNotices(){
        return $this->notices;
    }

    public function getNamePdf(){
        return $this->namePdf;
    }


    public function getResponse(){
        return $this->response;
    }
	


}