<?php
namespace App\Http\DTO\Estafeta\Tracking;

use Spatie\DataTransferObject\DataTransferObject;
use Spatie\DataTransferObject\FieldValidator as Validator;


class WaybillList extends DataTransferObject 
{


	/** 
	 * @var string  
	 * @param R= 'Codigo de rastreo' o G 
	 **/
	public $waybillType = "G";

	/** 
	 * @var array  
	 * @param '0000000000000000000022' 
	 **/
	public $waybills = array();


    
}

