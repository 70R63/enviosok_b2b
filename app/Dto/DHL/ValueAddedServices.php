<?php
namespace App\Dto\DHL;

use Spatie\DataTransferObject\DataTransferObject;
use Spatie\DataTransferObject\FieldValidator as Validator;


class ValueAddedServices extends DataTransferObject {

	#[String]
	public $serviceCode = "II";
	
	#[Doble]    
    public $value = 0.0;

    #[String]
    public $currency = "MXN";
}