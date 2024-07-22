<?php
namespace App\Dto\Estafeta;

use Spatie\DataTransferObject\DataTransferObject;

class OriginInfo extends DataTransferObject 
{
   
    public string $address1 = "address1";
    public string $address2 ="address2";
    public string $cellPhone= "cellPhone";
    public string $city = "city";
    public string $contactName= "contactName";
    public string $corporateName = "corporateName";
    public string $customerNumber = "0000000";
    public string $neighborhood = "neighborhood";
    public string $phoneNumber = "phoneNumber";
    public string $state = "state";
    public string $zipCode = "56585";
    public bool $valid = true;
   
}
