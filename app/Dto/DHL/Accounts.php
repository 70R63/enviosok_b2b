<?php
namespace App\Dto\DHL;

use Spatie\DataTransferObject\DataTransferObject;
use Spatie\DataTransferObject\FieldValidator as Validator;


class Accounts extends DataTransferObject {

	#[String]
	public $number = "980417663";

	#[String]
	public $typeCode = "shipper";
	
}