<?php
namespace App\Dto\Dhl;

use Spatie\DataTransferObject\DataTransferObject;
use Spatie\DataTransferObject\FieldValidator as Validator;

use Carbon\Carbon;

class Label extends DataTransferObject {

	/** @var string */
    public $productCode = "G";//G es para DOMESTICO ECONOMICO y N es para DOMESTICO EXPRESS.
	
    public $plannedShippingDateAndTime = "";
    
    public $pickup;

    public $accounts;

    public $outputImageProperties;

	public $customerDetails;

    public $content;
}