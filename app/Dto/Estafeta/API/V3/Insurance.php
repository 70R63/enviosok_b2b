<?php
namespace App\Dto\Estafeta\API\V3;

use Spatie\DataTransferObject\DataTransferObject;


class Insurance extends DataTransferObject {

	#[Max(100)]
	public string $contentDescription = "Descripcion del Contenido";


	public float $declaredValue = 0.3; 

}