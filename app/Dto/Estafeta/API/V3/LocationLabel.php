<?php
namespace App\Dto\Estafeta\API\V3;

use Spatie\DataTransferObject\DataTransferObject;

class LocationLabel extends DataTransferObject {
	public Location $origin;
	public Destination $destination;
	public Notified $notified;

}