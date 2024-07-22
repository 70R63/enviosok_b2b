<?php 

namespace App\Dto\Fedex;

use Spatie\DataTransferObject\DataTransferObject;
use Spatie\DataTransferObject\FieldValidator as Validator;


class Address extends DataTransferObject {

	public $streetLines;

	public $city;

	public $stateOrProvinceCode;

	public $postalCode;

	public $countryCode = "MX";

}