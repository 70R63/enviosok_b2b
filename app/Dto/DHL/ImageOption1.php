<?php
namespace App\Dto\DHL;

use Spatie\DataTransferObject\DataTransferObject;
use Spatie\DataTransferObject\FieldValidator as Validator;


class ImageOption1 extends DataTransferObject {

	#[String]
	public $typeCode = "label";

	#[String]
	public $templateName = "ECOM26_84_A4_001";

}