<?php 

namespace App\Negocio\Guias;

use Log;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

//SINGLENTON
use App\Singlenton\EstafetaDev;
use App\Singlenton\Estafeta;

//models
use App\Models\LtdTipoServicio;
use App\Models\Cliente;
use App\Models\Sucursal;
use App\Models\LtdCobertura;
use App\Models\Guia;
use App\Models\User;
use App\Models\GuiasPaquete;

use App\Negocio\Guias\Cotizacion as nCotizacion;
use App\Negocio\Saldos\Saldos as nSaldos;

use App\Dto\Guia as GuiaDTO;



Class EstafetaCreacion {

	private $response;
    private $cotizaciones;
	
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
     * 
     * @return json Objeto con la respuesta de exito o fallo 
     */

    public function parseoApiDev(array $data){
    	Log::debug(__CLASS__." ".__FUNCTION__." "." parseoApiDev");
        $data['numero_solicitud'] = Carbon::now()->timestamp;
    	Log::debug($data);

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $data = $this->validaUsuario($data);

    	Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $data = $this->validaCliente($data);

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $data = $this->validaSucursal($data);

    	Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
    	$data = $this->ltdTipoServicio($data);

    	Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $data = $this->validaLtdCobertura($data);

    	$data = $this->tarifas($data);

    	Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
    	$data = $this->cotizacion($data);

    	Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        if ($data['tipoPagoId'] ===2) {
            $this->saldo($data);    
        }
    	
        
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $cotizaciones = $this->resumenCotizacion($data);

    	Log::debug($data);
        
        $trackingNumber = "";
        $systemInformation = array("id"=>"AP01",
            "name"=>"AP01",
            "version"=>"1.10.20");
        $data['systemInformation']= $systemInformation;
        $data['labelDefinition']['serviceConfiguration']['quantityOfLabels'] = 1;
        $data['contenido'] = $data['labelDefinition']['wayBillDocument']['content'];
        
        Log::info("Se intancia el Singlento Estafeta");
        $sEstafeta = new EstafetaDev($data['ltd_id'], $data['empresa_id'], $data['esManual']);

        Log::debug(__CLASS__." ".__FUNCTION__." "." sEstafeta -> envio()");
        $sEstafeta -> envio($data);


        $this->response = $sEstafeta->getResultado();
        Log::debug(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $this->response->cotizacion=$cotizaciones;

        $trackingNumber = $sEstafeta->getTrackingNumber();
        $this->notices[] ="Exito";
        $this->notices[]= sprintf("El registro de la solicitud se genero con exito con el ID xxxx");
        

    }


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
     * 
     * @return json Objeto con la respuesta de exito o fallo 
     */

    public function parseoApi(array $data){
        Log::debug(__CLASS__." ".__FUNCTION__." "." parseoApi");
        $data['numero_solicitud'] = Carbon::now()->timestamp;
        Log::debug($data);

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $data = $this->validaUsuario($data);

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $data = $this->validaCliente($data);

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $data = $this->validaSucursal($data);

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $data = $this->ltdTipoServicio($data);

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $data = $this->validaLtdCobertura($data);      

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
	
    	$data = $this->perseoCotizacion($data);
    	//Log::debug(print_r($data,true));
    	$data = $this->tarifas($data);
        

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
	    //$data = $this->tarifas($data);
	   
        $data = $this->cotizacion($data);
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        Log::debug($data);
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $this->saldo($data);    
        
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $cotizaciones = $this->resumenCotizacion($data);
        
        //Log::debug($data);
        
        $trackingNumber = "";
        $systemInformation = array("id"=>"AP01",
            "name"=>"AP01",
            "version"=>"1.10.20");
        $data['systemInformation']= $systemInformation;
        $data['labelDefinition']['serviceConfiguration']['quantityOfLabels'] = 1;
        $data['contenido'] = $data['labelDefinition']['wayBillDocument']['content'];

	Log::debug("Se intancia el Singlento Estafeta");
	$tipo = 1;
        $sEstafeta = new Estafeta($data['empresa_id'], $data["esManual"] ,$tipo);

        Log::debug(__CLASS__." ".__FUNCTION__." "." sEstafeta -> envio()");
        $sEstafeta -> envio($data);


        $this->response = $sEstafeta->getResultado();

        Log::debug(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $this->response->cotizacion=$cotizaciones;
        
        $trackingNumbers = $sEstafeta->getTrackingNumber();
        Log::debug(print_r($trackingNumbers ,true));      
        $data['tracking_number'] =$trackingNumbers;
        $carbon = Carbon::now();
        $unique = md5( (string)$carbon);
        $carbon->settings(['toStringFormat' => 'Y-m-d-H-i-s.u']);

        $formatoExtension = ($data['formatoImpresion'] === "FILE_PDF") ? ".pdf" : ".txt" ;   
        $namePdf = sprintf("%s-%s-%s%s",(string)$carbon,$data['empresa_id'],$unique, $formatoExtension);
        $data['documento'] = $namePdf;
        Storage::disk('public')->put($namePdf,base64_decode($sEstafeta->documento));

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        unset($data['identification']);
        unset($data['systemInformation']);
        unset($data['id']);
        unset($data['created_at']);
        unset($data['updated_at']);

        $data['canal']= $data['esManual'];
        

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        //Log::debug($data);
        $id = Guia::create($data)->id;
        $this->notices[] ="Exito";
        $this->notices[] = sprintf("El registro de la solicitud se genero con exito con el ID %s ", $id);

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $data = $this->validaPaquete($data, $id);
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);

    }

	
    /**
     * Se busca obtener las tarifas de ESTAFETA basado en el KG y parseando valores del body custom.
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
     * @param  Illuminate\Http\Request  $request Recibe la paticion del cliente
     * 
     * @var array $data Se convierte el Json de la peticion a array
     * 
     * @return json Objeto con la respuesta de exito o fallo 
     */

    public function soloCotizacion(array $data){
        Log::debug(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $data['numero_solicitud'] = Carbon::now()->timestamp;
        Log::debug($data);

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $data = $this->ltdTipoServicio($data);

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $data = $this->validaLtdCobertura($data);

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $data = $this->tarifas($data);

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $data = $this->cotizacion($data, 2);

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $cotizaciones = $this->resumenCotizacion($data);

        Log::debug($cotizaciones);
        $this->cotizaciones = $cotizaciones;
        $this->response = $cotizaciones;
       
        $this->notices[] ="Exito";
        $this->notices[]= sprintf("La cotizacion puede cambiar al momento de creacar de la guia");
        

    }



    /**
     * Busca el usuario .
     * 
     * @author Javier Hernandez
     * @copyright 2022-2023 XpertaMexico
     * @package App\Negocio\Guias
     * @api
     * 
     * @version 1.0.0
     * 
     * @since 1.0.0 Primera version de la funcion validaUsuario
     * 
     * @throws
     *
     * @param array  $data Array conta la infroamcion del flujo
     * 
     * @var array $data Se convierte el Json de la peticion a array
     * 
     * @return json Objeto con la respuesta de exito o fallo 
     */

    public function validaUsuario(array $data){
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $user = User::where("id", $data['user_id'])
            ->firstOrFail();

        Log::debug($user);

        $data['empresa_id']= $user->empresa_id;
        $data['usuario']= $user->name;
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        return $data;
    }
    

    /**
     * Busca los valor de LTD servicio por empresaId y servicioID .
     * 
     * @author Javier Hernandez
     * @copyright 2022-2023 XpertaMexico
     * @package App\Negocio\Guias
     * @api
     * 
     * @version 1.0.0
     * 
     * @since 1.0.0 Primera version de la funcion ltdTipoServicio
     * 
     * @throws
     *
     * @param array  $data Array conta la infroamcion del flujo
     * 
     * @var array $data Se convierte el Json de la peticion a array
     * 
     * @return json Objeto con la respuesta de exito o fallo 
     */

    public function ltdTipoServicio(array $data){

    	Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);

    	$ltdTipoServicios = LtdTipoServicio::where("empresa_id", $data['empresa_id'])
    					->where("ltd_id", $data['ltd_id'])
    					->where("service_id",$data['servicio_id'])
    					->get()
    					->toArray()
    					;

    	Log::debug(print_r($ltdTipoServicios,true));
        if (count($ltdTipoServicios) ==1) {
            $ltdTipoServicio = $ltdTipoServicios[0];
        } else {
            throw ValidationException::withMessages(array("No cuenta con el servcio indicado o tiene incongurencias de Servicios, Validar con el Administrador."));
        }


    	$identification = array(
            "suscriberId"=>$ltdTipoServicio['client_id']
            ,"customerNumber"=>$ltdTipoServicio['customer_number']
        );

        $data['identification']=$identification;
        $data['labelDefinition']['serviceConfiguration']['salesOrganization']=$ltdTipoServicio['sales_organization']; 
        
        $data['labelDefinition']['serviceConfiguration']['serviceTypeId'] = $ltdTipoServicio['service_id_ltd'];

    	Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
    	return $data;
    }


    /**
     * Revisa si hay terifas para el licente usando Flat.
     * 
     * @author Javier Hernandez
     * @copyright 2022-2023 XpertaMexico
     * @package App\Negocio\Guias
     * @api
     * 
     * @version 1.0.0
     * 
     * @since 1.0.0 Primera version de la funcion tarifas
     * 
     * @throws
     *
     * @param array  $data Array conta la infroamcion del flujo
     * 
     * @var array $data Se convierte el Json de la peticion a array
     * 
     * @return json Objeto con la respuesta de exito o fallo 
     */

    public function tarifas(array $data){
    	Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);

    	$nCotizacion = new nCotizacion();

    	$nCotizacion->base($data, $data['ltd_id'],$data['esManual']);
        $data['tipoPagoId'] = $nCotizacion->getTipoPagoId();
    	Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
    	$cotizacion = $nCotizacion->getTabla();

    	if (count($cotizacion) <1) {
    		throw ValidationException::withMessages(array("No cuenta con tarifas."));
    	}

        unset($cotizacion[0]['extendida']);
        

    	$data = array_merge($data, $cotizacion[0]);

    	Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
    	return $data;
    }


    /**
     * Valida la existencia del cliente en caso contrario parsea el request
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
         
        $labelDefinition = $data['labelDefinition'];
        $locations = $labelDefinition['location'];

        $destination = $locations['destination'];
        $homeAddress = $destination['homeAddress'];

       	$contacto = $homeAddress['contact'];
       	$direccion = $homeAddress['address'];

       	$data['nombre_d'] = $contacto['contactName'];
       	$data['cp_d']   = $direccion['zipCode'];
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $cliente = new Cliente();
        $cliente->validaCliente($data);

        if ( !$cliente->getExiste() ) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." Cliente no existe");

            $data['contacto_d'] = $contacto['contactName'];
            $data['direccion_d']= $direccion['roadName'];
            $data['direccion2_d']=$direccion['addressReference'];
            $data['colonia_d']  = $direccion['settlementName'];
            $data['ciudad_d']   = "";
            $data['entidad_federativa_d']="";
            $data['celular_d']  = $contacto['cellPhone'];
            $data['telefono_d'] = $contacto['cellPhone'];;
            $data['no_ext_d']   = $direccion['externalNum'];
            $data['no_int_d']   = '';
            
    
            $cliente->insertSemiManual($data);

        }
        $data['cliente_id']=$cliente->getId();
        $data['cia_d']=$data['cliente_id'];

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


        $labelDefinition = $data['labelDefinition'];
        $locations = $labelDefinition['location'];

        $origen = $locations['origin'];
        
       	$contacto = $origen['contact'];
       	$direccion = $origen['address'];

       	$data['nombre'] = $contacto['contactName'];
       	$data['cp']   = $direccion['zipCode'];

        $remitente = new Sucursal();
        $remitente->existe($data);
        
        if ( !$remitente->getExiste() ) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);

            $data['contacto'] = $contacto['contactName'];
            $data['direccion']= $direccion['roadName'];
            $data['direccion2']=$direccion['addressReference'];
            $data['colonia']  = $direccion['settlementName'];
            $data['ciudad']   = "";
            $data['entidad_federativa']="";
            $data['celular']  = $contacto['cellPhone'];
            $data['telefono'] = $contacto['cellPhone'];;
            $data['no_ext']   = $direccion['externalNum'];
            $data['no_int']   = '';
            

            $remitente->insertParse($data);
        }
        $data['sucursal_id']=$remitente->getId();
        $data['sucursal'] = $data['sucursal_id'];
        $data['cia'] = $data['sucursal_id'];

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
     * @param int $cotizacionPersonalizada = 1 es el flujo de guia con 
     * valores del body original para crear una guia
     * @param int $cotizacionPersonalizada = 2 flujo de una cotizacion sin 
     * creacion de guia 
     * 
     * @var int 
     * 
     * 
     * @return $data Se agra informacion segun la necesidad
     */

    public function cotizacion($data, $cotizacionPersonalizada=1){
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);

        $data['piezas']=1;
        $data['costo_seguro']=0;
        
        $data['costo_base']= isset($data['costo']) ? $data['costo']      : 1;//$data['costo'] ;

        switch ($cotizacionPersonalizada) {
            case '1':
                Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
                $data['valor_envio']=0;
                $dimensiones = $data['labelDefinition']['itemDescription'];

                $serviceConfiguration = $data['labelDefinition']['serviceConfiguration'];

                if($serviceConfiguration['isInsurance']){
                    $data['valor_envio'] = $serviceConfiguration['insurance']['declaredValue'];
                }
            
                $data['peso'] = $dimensiones['weight'];
                $data['alto'] = $dimensiones['height'];
                $data['ancho'] = $dimensiones['width'];
                $data['largo'] = $dimensiones['length'];
                break;
            
            case '2':
                Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
                break;
            default:
                // code...
                break;
        }

        
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $data['costo_seguro'] = ($data['valor_envio']*$data['seguro'])/100;

        $data['peso_bascula'] = $data['peso'];
        $data['peso_dimensional'] = ($data['alto']*$data['ancho']*$data['largo'])/5000;
        

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $data['pesoFacturado'] = ($data['peso_bascula'] > $data['peso_dimensional']) ? ceil($data['peso_bascula']) : ceil($data['peso_dimensional']) ;


        //caclulo extendida 
        $data['costo_extendida'] = 0;
        if ($data['extendida'] === 'SI') {
            $data['costo_extendida']=$data['exceso_dimension'];
        }


    	$data['costo_kg_extra']=0;
        $data['sobre_peso_kg']=0;
    	if ($data['pesoFacturado'] >$data['kg_fin']) {
    		$data['sobre_peso_kg'] = $data['pesoFacturado']-$data['kg_fin'];
    		$data['costo_kg_extra'] = $data['sobre_peso_kg']*$data['kg_extra'];
    	}


        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $data['subPrecio'] = $data['costo_base']+$data['costo_kg_extra']+$data['costo_seguro']+$data['costo_extendida'];

        $data['precio']=$data['subPrecio']*1.16;
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        Log::debug(print_r(['data'],true));


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
     * @param int $cotizacionPersonalizada = 1 es el flujo de guia con 
     * valores del body original para crear una guia
     * @param int $cotizacionPersonalizada = 2 flujo de una cotizacion sin 
     * creacion de guia 
     * 
     * @var int 
     * 
     * 
     * @return $data Se agra informacion segun la necesidad
     */

    public function perseoCotizacion($data, $cotizacionPersonalizada=1){
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        switch ($cotizacionPersonalizada) {
            case '1':
                $data['valor_envio']=0;
                $dimensiones = $data['labelDefinition']['itemDescription'];

                $serviceConfiguration = $data['labelDefinition']['serviceConfiguration'];


            
                if($serviceConfiguration['isInsurance']){
                    $data['valor_envio'] = $serviceConfiguration['insurance']['declaredValue'];
                }
            
                $data['peso'] = $dimensiones['weight'];
                $data['alto'] = $dimensiones['height'];
                $data['ancho'] = $dimensiones['width'];
                $data['largo'] = $dimensiones['length'];
                break;
            
            case '2':
                Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
                break;
            default:
                // code...
                break;
        }


        $data['peso_bascula'] = $data['peso'];
        $data['peso_dimensional'] = ($data['alto']*$data['ancho']*$data['largo'])/5000;
        

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $data['pesoFacturado'] = ($data['peso_bascula'] > $data['peso_dimensional']) ? ceil($data['peso_bascula']) : ceil($data['peso_dimensional']) ;


        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        return $data;

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
        if ($data['tipoPagoId'] ===2) {
            $saldo->validaSaldo($monto);
        }
        

        $saldo->menosPrecio($data["sucursal_id"], $data["precio"]);
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
    }


    /**
     * Valida el paquete con dimensinoes para registrar
     * 
     * @author Javier Hernandez
     * @copyright 2022-2023 XpertaMexico
     * @package App\Negocio\Guias
     * 
     * @version 1.0.0
     * 
     * @since 1.0.0 Primera version de la funcion validaPaquete
     * 
     * @throws
     *
     * @param array $data Informacion general de la peticion
     * @param int $id El id de la guia insertada
     * 
     * @var float $monto
     * 
     * 
     * @return $data Se agra informacion segun la necesidad
     */

    public function validaPaquete($data, $id){
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $key = 0;
        $boolPrecio = false;
        $plataforma= $data['canal'];
        $guiaPaqueteInsert = GuiaDTO::validaPiezasPaquete($data, $key, $boolPrecio, $id,$plataforma);
        

        $idGuiaPaquete = GuiasPaquete::create($guiaPaqueteInsert)->id;
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." idGuiaPaquete =$idGuiaPaquete");
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

    public function resumenCotizacion($data){
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $resumenCotizacion['costo'] = $data['costo'];
        $resumenCotizacion['kgs_extras'] = $data['sobre_peso_kg'];
        $resumenCotizacion['costo_kgs_extras'] = $data['costo_kg_extra'];
        $resumenCotizacion['valor_declarado'] = $data['valor_envio'];
        $resumenCotizacion['costo_seguro'] = $data['costo_seguro'];
        $resumenCotizacion['costo_ae'] = $data['costo_extendida'];
        $resumenCotizacion['sub_total'] = $data['subPrecio'];
        $resumenCotizacion['total'] = $data['precio'];

        $resumen[] = $resumenCotizacion;
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        return $resumen;
        
    }

    public function getResponse(){
        return $this->response;
    }

    public function getNotices(){
        return $this->notices;
    }

    public function getCotizaciones(){
        return $this->cotizaciones;
    }


}
