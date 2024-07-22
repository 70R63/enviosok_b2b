<?php 

namespace App\Dto\Fedex;

use Spatie\DataTransferObject\DataTransferObject;
use Spatie\DataTransferObject\FieldValidator as Validator;

class Weight extends DataTransferObject {

	public $units = "KG";

	public $value = 1;

}