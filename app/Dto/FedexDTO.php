<?php

namespace App\Dto;

use App\Dto\Fedex\Etiqueta;
use App\Dto\Fedex\RequestedShipment;
use App\Dto\Fedex\AccountNumber;
use App\Dto\Fedex\Shipper;
use App\Dto\Fedex\Recipients;
use App\Dto\Fedex\Contact;
use App\Dto\Fedex\Address;
use App\Dto\Fedex\RequestedPackageLineItems;
use App\Dto\Fedex\DeclaredValue;
use App\Dto\Fedex\Weight;

use Log;

/**
 * 
 */
class FedexDTO 
{
	/**
     * Insert , array que conse usara para insertar como si fura un request
     *
     * @var nombre
     */
    public $insert;
	
	function __construct()
	{
		// code...
	}

	/**
	 * Metodo para asignar los valores del request a una etiqueta 
	 * para generar el venvio a la api de Fedex
	 * https://developer.fedex.com/api/en-ph/guides/api-reference.html
	 * 
	 * @param \Illuminate\Http\Request $request
	 * @return App\Dto\Fedex\Etiqueta Etiqueta
	 */

	public function parser($request){
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." INICIANDO");
		Log::info(($request['contacto']));
		
		$weight = new Weight(array('value'=> $request['peso_facturado']));

		$contactShipper = New Contact( 
			array("personName" 	=> $this->quitar_acentos($request['contacto'])
				,"phoneNumber"	=> $request['celular']
				,"companyName"	=> $this->quitar_acentos($request['nombre'])
				) 
			);
		Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
		$direccion = sprintf("%s %s %s,%s",$request['direccion'],$request['no_int'],$request['no_ext'],$request['direccion2'] );
		$streetLines = str_split($this->quitar_acentos($direccion),35);

		//Validacion temporal Entidad Federativa
		Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
		if (strlen($request['entidad_federativa']) ===2 ){
			Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
			$stateOrProvinceCode = $request['entidad_federativa'];
		} else{
			Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
			$stateOrProvinceCode = config('general.stateOrProvinceCode')[$request['entidad_federativa']];
		}
		Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
		$addressShipper = New Address(
			array("streetLines"     => $streetLines
				,"city"	=> $this->quitar_acentos($request['colonia'])
				,"stateOrProvinceCode"	=> $stateOrProvinceCode
				,"postalCode"	=> $request['cp']
			));
		Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
		$contactRecipients = New Contact( 
			array("personName" 	=> $this->quitar_acentos($request['contacto_d'])
				,"phoneNumber"	=> $request['celular_d']
				,"companyName"	=> $this->quitar_acentos($request['nombre_d'])) 
			);

		Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
		$direccion_d = sprintf("%s %s %s,%s",$request['direccion_d'],$request['no_int_d'],$request['no_ext_d'],$request['direccion2_d'] );
		$streetLines_d = str_split($this->quitar_acentos($direccion_d),35);

		if (strlen($request['entidad_federativa_d']) ===2 ){
			$stateOrProvinceCode_d = $request['entidad_federativa_d'];
		} else{
			$stateOrProvinceCode_d = config('general.stateOrProvinceCode')[$request['entidad_federativa_d']];
		}

		Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." INICIANDO");
		Log::debug(print_r($streetLines_d,true));

		$addressRecipients = New Address(
			array("streetLines"     => $this->quitar_acentos($streetLines_d)
				,"city"	=> $this->quitar_acentos($request['colonia_d'])
				,"stateOrProvinceCode"	=> $stateOrProvinceCode_d
				,"postalCode"	=> $request['cp_d']
			));
		Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
		$shipper = new Shipper(array('contact' => $contactShipper, 'address' => $addressShipper ));

		$recipients = New Recipients(array('contact' => $contactRecipients, 'address' => $addressRecipients ));
		Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
		$declaredValueWeight = array('declaredValue' => new DeclaredValue(["amount"=>$request['valor_envio']])
                                    ,'weight' => $weight
                                    ,'groupPackageCount' => $request['piezas'] 
                                    ,'itemDescriptionForClearance' => $this->quitar_acentos($request['contenido'])
                                );

		$requestedPackageLineItems = New RequestedPackageLineItems($declaredValueWeight);
		Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $requestedShipment = 
            array('shipper' => $shipper
            ,'recipients' => array($recipients)
            ,'requestedPackageLineItems' => array($requestedPackageLineItems)
            ,'serviceType'		=> Config('ltd.fedex.servicio')[$request['servicio_id']]
        );


        $accountNumber = new AccountNumber([
        		'value' => config('ltd.fedex.cred.accountNumber')
        	]
        );
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $init = array('requestedShipment'   => new RequestedShipment($requestedShipment)
	                    ,'accountNumber'    => $accountNumber );
        
        Log::info(__CLASS__." ".__FUNCTION__." INICIANDO");
        return new Etiqueta($init);

	}


	/* Función que elimina los acantos y letras ñ*/
	private function quitar_acentos($cadena){
	    //Reemplazamos la A y a
		$cadena = str_replace(
		array('Á', 'À', 'Â', 'Ä', 'á', 'à', 'ä', 'â', 'ª'),
		array('A', 'A', 'A', 'A', 'a', 'a', 'a', 'a', 'a'),
		$cadena
		);

		//Reemplazamos la E y e
		$cadena = str_replace(
		array('É', 'È', 'Ê', 'Ë', 'é', 'è', 'ë', 'ê'),
		array('E', 'E', 'E', 'E', 'e', 'e', 'e', 'e'),
		$cadena );

		//Reemplazamos la I y i
		$cadena = str_replace(
		array('Í', 'Ì', 'Ï', 'Î', 'í', 'ì', 'ï', 'î'),
		array('I', 'I', 'I', 'I', 'i', 'i', 'i', 'i'),
		$cadena );

		//Reemplazamos la O y o
		$cadena = str_replace(
		array('Ó', 'Ò', 'Ö', 'Ô', 'ó', 'ò', 'ö', 'ô'),
		array('O', 'O', 'O', 'O', 'o', 'o', 'o', 'o'),
		$cadena );

		//Reemplazamos la U y u
		$cadena = str_replace(
		array('Ú', 'Ù', 'Û', 'Ü', 'ú', 'ù', 'ü', 'û'),
		array('U', 'U', 'U', 'U', 'u', 'u', 'u', 'u'),
		$cadena );

		//Reemplazamos la N, n, C y c
		$cadena = str_replace(
		array('Ñ', 'ñ', 'Ç', 'ç'),
		array('N', 'n', 'C', 'c'),
		$cadena
		);
	    return $cadena;
	}
}