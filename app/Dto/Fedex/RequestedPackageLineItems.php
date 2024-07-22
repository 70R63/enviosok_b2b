<?php 

namespace App\Dto\Fedex;

use Spatie\DataTransferObject\DataTransferObject;
use Spatie\DataTransferObject\FieldValidator as Validator;

use App\Dto\Fedex\DeclaredValue;
use App\Dto\Fedex\Weight;

class RequestedPackageLineItems extends DataTransferObject {

	public $groupPackageCount=1;

	public $itemDescriptionForClearance = "Contenido del envio sin descripcion";

	public DeclaredValue $declaredValue;

	public Weight $weight;

}