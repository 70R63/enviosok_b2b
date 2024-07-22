<?php
namespace App\Dto\DHL;

use Spatie\DataTransferObject\DataTransferObject;
use Spatie\DataTransferObject\FieldValidator as Validator;


class ContentSeguro extends DataTransferObject {

	/** @var string */
    public $unitOfMeasurement = "metric";

    /** @var string */
    public $incoterm = "DAP";

    #[Bool]
    public $isCustomsDeclarable = false;

    /** @var string */
    public $description = "Sin Descripcion";


	public $packages = array();

	#[String]
	public $declaredValueCurrency  = "MXP";

    #[Double]
    public $declaredValue;
}
