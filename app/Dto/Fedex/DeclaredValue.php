<?php 

namespace App\Dto\Fedex;

use Spatie\DataTransferObject\DataTransferObject;
use Spatie\DataTransferObject\FieldValidator as Validator;

class DeclaredValue extends DataTransferObject {

	public $amount;

	public $currency = "NMP";

}