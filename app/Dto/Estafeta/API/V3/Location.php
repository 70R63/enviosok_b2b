<?php
namespace App\Dto\Estafeta\API\V3;

use Spatie\DataTransferObject\DataTransferObject;


class Location extends DataTransferObject {

	public Contact $contact;
	public Address $address;
	

}