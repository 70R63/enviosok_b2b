<?php
namespace App\Dto\Estafeta\Tracking;

use Spatie\DataTransferObject\DataTransferObject;
use Spatie\DataTransferObject\FieldValidator as Validator;

use App\Dto\Estafeta\Tracking\HistoryConfiguration;
use App\Dto\Estafeta\Tracking\FilterType;

class SearchConfiguration extends DataTransferObject 
{

	/** 
	 * @var bool  
	 * @param false o true
	 **/
	public $includeDimensions = false;

	/** 
	 * @var bool
	 * @param  false o true 
	 **/
	public $includeWaybillReplaceData = false ;

	/** 
	 * @var bool
	 * @param true o false
	 **/
	public $includeReturnDocumentData = false ;

	/** 
	 * @var bool
	 * @param true o false
	 **/
	public $includeMultipleServiceData = false ;

	/** 
	 * @var bool
	 * @param true o false
	 **/
	public $includeInternationalData = false ;

	/** 
	 * @var bool
	 * @param true o false
	 **/
	public $includeSignature = false ;

	/** 
	 * @var bool
	 * @param true o false
	 **/
	public $includeCustomerInfo = false ;

	/** @var App\Http\DTO\Estafeta\Tracking\HistoryConfiguration */
	public HistoryConfiguration $historyConfiguration;
	public FilterType $filterType;

    
}
