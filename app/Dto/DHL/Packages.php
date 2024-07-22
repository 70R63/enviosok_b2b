<?php
namespace App\Dto\DHL;

use Spatie\DataTransferObject\DataTransferObject;
use Spatie\DataTransferObject\FieldValidator as Validator;


class Packages extends DataTransferObject {

	public $customerReferences = array();
	
	#[Doble]
	public $weight = 0;

	public $dimensions = array();
}