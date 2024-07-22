<?php
namespace App\Dto\DHL;

use Spatie\DataTransferObject\DataTransferObject;
use Spatie\DataTransferObject\FieldValidator as Validator;


class ContactInformation extends DataTransferObject {

	#[String]
	public $phone = "";

	#[String]
	public $companyName = "";

	#[String]
	public $fullName = "";

	#[String]
	public $email = "receiver@email.com";

	
}

