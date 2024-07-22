<?php
namespace App\Dto\DHL;

use Spatie\DataTransferObject\DataTransferObject;
use Spatie\DataTransferObject\FieldValidator as Validator;


class CustomerDetails extends DataTransferObject {

	public $customerDetails = array(); 
}
