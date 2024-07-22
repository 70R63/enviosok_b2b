<?php
namespace App\Dto\Estafeta;

use Spatie\DataTransferObject\DataTransferObject;
use Spatie\DataTransferObject\FieldValidator as Validator;

use App\Dto\Estafeta\LabelDescriptionList;

class Label extends DataTransferObject 
{

    /** @var string */
    public $suscriberId = "00";
    
    /** @var string */
    public $customerNumber = "0000000";

     /** @var string */
    public $password = "lAbeL_K_11";

     /** @var string */
    public $login = "prueba1";

     /** @var boolean */
    public $valid = true;
    
    /** @var int */
    public $quadrant = 0;

    /** @var int */
    public $paperType = 1;

    /** @var int */
    public $labelDescriptionListCount = 1;    

    public LabelDescriptionList $labelDescriptionList ; 

    
}
