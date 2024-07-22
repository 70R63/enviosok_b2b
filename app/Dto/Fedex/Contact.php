<?php 

namespace App\Dto\Fedex;

use Spatie\DataTransferObject\DataTransferObject;
use Spatie\DataTransferObject\FieldValidator as Validator;


class Contact extends DataTransferObject {

	public $personName;

	public $phoneNumber;

	public $companyName;

}