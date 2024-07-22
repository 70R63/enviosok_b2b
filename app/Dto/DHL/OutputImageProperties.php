<?php
namespace App\Dto\DHL;

use Spatie\DataTransferObject\DataTransferObject;
use Spatie\DataTransferObject\FieldValidator as Validator;


class OutputImageProperties extends DataTransferObject {

	public $imageOptions = array(); 

	#[Bool]
	public $splitTransportAndWaybillDocLabels = true;

	#[Bool]
	public $allDocumentsInOneImage = true;

	#[Bool]
	public $splitDocumentsByPages = true;

	#[Bool]
	public $splitInvoiceAndReceipt = true;
}
