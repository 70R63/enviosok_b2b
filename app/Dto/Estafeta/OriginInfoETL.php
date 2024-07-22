<?php
namespace App\Http\Dto\Estafeta;


class OriginInfoETL 
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
   
   /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(array $remitente)
    {
        $this ->address1 = $remitente;
      /*
        $this -> ;
        $this -> ;
        $this -> ;
        $this -> ;
        $this -> ;
        $this -> ;
        $this -> ;
        $this -> ;
        */
    }
}

?>