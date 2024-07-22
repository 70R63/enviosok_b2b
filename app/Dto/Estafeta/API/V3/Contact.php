<?php
namespace App\Dto\Estafeta\API\V3;

use Spatie\DataTransferObject\DataTransferObject;

class Contact extends DataTransferObject {
	
	#[Max(50)]
	public string $corporateName = "corporateName";

	#[Max(30)]
	public string $contactName = "contactName";

	#[Max(20)]
	public string $cellPhone = "cellPhone";
	
	#[Email]
	public string $email = "sinmail@gmail.com";
	
	#[Max(13)]
	public string $taxPayerCode = "XAXX010101000";
	

}