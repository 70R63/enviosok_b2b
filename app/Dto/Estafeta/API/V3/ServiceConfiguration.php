<?php
namespace App\Dto\Estafeta\API\V3;

use Spatie\DataTransferObject\DataTransferObject;


class ServiceConfiguration extends DataTransferObject {
	
	public int $quantityOfLabels=1;
	
	#[Max(2)]	
	public string $serviceTypeId="00";

	public string $salesOrganization = "000";

	#[Max(5)]
	public string $originZipCodeForRouting = "00000";

	public bool $isInsurance = false;

	public $insurance;

	public bool $isReturnDocument = FALSE;

	public string $effectiveDate = "";
	
	
}