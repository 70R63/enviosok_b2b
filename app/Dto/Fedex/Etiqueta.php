<?php

namespace App\Dto\Fedex;

use Spatie\DataTransferObject\DataTransferObject;
use Spatie\DataTransferObject\FieldValidator as Validator;

use App\Dto\Fedex\RequestedShipment;
use App\Dto\Fedex\AccountNumber;

class Etiqueta extends DataTransferObject {

	public $labelResponseOptions = "URL_ONLY";

	public RequestedShipment $requestedShipment ;

	public AccountNumber $accountNumber;

}

