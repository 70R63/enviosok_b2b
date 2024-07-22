<?php
namespace App\Http\DTO\Estafeta\Tracking;

use Spatie\DataTransferObject\DataTransferObject;
use Spatie\DataTransferObject\FieldValidator as Validator;


class WaybillRange extends DataTransferObject 
{


	/** 
	 * @var string  
	 * @param '0000000000000000000000' 
	 **/
	public $initialWaybill = "";

	/** 
	 * @var string  
	 * @param '0000000000000000000022' 
	 **/
	public $finalWaybill = "";


    
}

