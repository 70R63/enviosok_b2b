<?php
namespace App\Http\DTO\Estafeta\Tracking;

use Spatie\DataTransferObject\DataTransferObject;
use Spatie\DataTransferObject\FieldValidator as Validator;


class FilterType extends DataTransferObject 
{


	/** 
	 * @var bool  
	 * @param false o true 
	 **/
	public $filterInformation = true;

	/** 
	 * @var string  
	 * @param ON_TRANSIT, DELIVERED, RETURNED 
	 **/
	public $filterType = "ON_TRANSIT";


    
}

