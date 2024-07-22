<?php 

namespace App\Dto\Fedex;

use Spatie\DataTransferObject\DataTransferObject;
use Spatie\DataTransferObject\FieldValidator as Validator;

use App\Dto\Fedex\Contact;
use App\Dto\Fedex\Address;

class Shipper extends DataTransferObject {

	public Contact $contact;

	public Address $address;

}