<?php
namespace App\Dto\DHL;

use Spatie\DataTransferObject\DataTransferObject;
use Spatie\DataTransferObject\FieldValidator as Validator;


class Dimensions extends DataTransferObject {

	#[Doble]
	public $length = 0.0;

	#[Doble]
	public $width = 0.0;

	#[Doble]
	public $height = 0.0;
}