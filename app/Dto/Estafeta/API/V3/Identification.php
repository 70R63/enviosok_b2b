<?php
namespace App\Dto\Estafeta\API\V3;

use Spatie\DataTransferObject\DataTransferObject;


class Identification extends DataTransferObject {
	public string $suscriberId = "00";
    public string $customerNumber ="0000000";
}