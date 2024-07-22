<?php
namespace App\Http\DTO\Estafeta\Tracking;

use Spatie\DataTransferObject\DataTransferObject;
use Spatie\DataTransferObject\FieldValidator as Validator;


use App\Http\DTO\Estafeta\Tracking\WaybillRange;
use App\Http\DTO\Estafeta\Tracking\WaybillList;

class SearchType extends DataTransferObject 
{


	/** 
	 * @var string  
	 * @param R o L 
	 **/
	public $type = "L";

	
	/** @var App\Http\DTO\Estafeta\Tracking\WaybillRange */
	public WaybillRange $waybillRange ;

	/** @var App\Http\DTO\Estafeta\Tracking\WaybillList */
	public WaybillList $waybillList;


    
}

