<?php
namespace App\Dto\Estafeta\API\V3;

use Spatie\DataTransferObject\DataTransferObject;

class Notified extends DataTransferObject {
	
	/** @reference Location 3.1.2.4.1 */
	public Location $residence;
}