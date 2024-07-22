<?php
namespace App\Http\DTO\Estafeta\Tracking;

use Spatie\DataTransferObject\DataTransferObject;
use Spatie\DataTransferObject\FieldValidator as Validator;


class HistoryConfiguration extends DataTransferObject 
{


	/** 
	 * @var bool  
	 * @param false o true 
	 **/
	public $includeHistory = false;

	/** 
	 * @var string  
	 * @param ALL, ONLY_EXCEPTIONS, LAST_EVENT 
	 **/
	public $historyType = "LAST_EVENT";


    
}

