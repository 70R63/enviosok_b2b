<?php
namespace App\Dto\DHL;

use Spatie\DataTransferObject\DataTransferObject;
use Spatie\DataTransferObject\FieldValidator as Validator;


class AddressDetails extends DataTransferObject {

	public $shipperDetails = array();
	public $receiverDetails = array();
}

