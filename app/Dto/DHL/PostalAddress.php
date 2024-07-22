<?php
namespace App\Dto\DHL;

use Spatie\DataTransferObject\DataTransferObject;
use Spatie\DataTransferObject\FieldValidator as Validator;


class PostalAddress extends DataTransferObject {

	#[String]
	public $cityName = "";

	#[String]
	public $countryCode = "MX";

	#[String]
	public $postalCode = "";

	#[String]
	public $addressLine1 = "";

	#[String]
	public $addressLine2 = "";

	#[String]
	public $addressLine3 = "";
	
}

