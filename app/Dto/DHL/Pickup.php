<?php
namespace App\Dto\DHL;

use Spatie\DataTransferObject\DataTransferObject;
use Spatie\DataTransferObject\FieldValidator as Validator;


class Pickup extends DataTransferObject {

	#[Bool]
	public $isRequested = false;
}
