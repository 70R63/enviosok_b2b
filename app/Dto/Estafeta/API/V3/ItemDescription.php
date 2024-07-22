<?php
namespace App\Dto\Estafeta\API\V3;

use Spatie\DataTransferObject\DataTransferObject;


class ItemDescription extends DataTransferObject {
	public int $parcelId =4;
	public float $weight =1;
	public int $height =1;
	public int $length =1;
	public int $width =1;
}