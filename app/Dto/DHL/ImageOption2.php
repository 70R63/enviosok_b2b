<?php
namespace App\Dto\DHL;

use Spatie\DataTransferObject\DataTransferObject;
use Spatie\DataTransferObject\FieldValidator as Validator;


class ImageOption2 extends DataTransferObject {

	#[String]
	public $typeCode = "waybillDoc";

	#[String]
	public $templateName = "ARCH_8X4_A4_002";

	#[Bool]
	public $isRequested = true;

	#[Bool]
	public $hideAccountNumber = true;

	#[Int]
	public $numberOfCopies = 1;	


}