<?php
namespace App\Dto\Estafeta\API\V3;

use Spatie\DataTransferObject\DataTransferObject;


class SystemInformation extends DataTransferObject {
	public string $id = "AP01";
    public string $name ="AP01";
    public string $version ="1.10.20";
}