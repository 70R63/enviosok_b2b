<?php
namespace App\Dto\Estafeta\API\V3;

use Spatie\DataTransferObject\DataTransferObject;

class Destination extends DataTransferObject {
	 
	public bool $isDeliveryToPUDO=FALSE ;

	public Location $homeAddress;

}