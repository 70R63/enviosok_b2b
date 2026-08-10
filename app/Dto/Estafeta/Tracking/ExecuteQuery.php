<?php
namespace App\Dto\Estafeta\Tracking;

use Spatie\DataTransferObject\DataTransferObject;
use Spatie\DataTransferObject\FieldValidator as Validator;


use App\Dto\Estafeta\Tracking\SearchType;
use App\Dto\Estafeta\Tracking\SearchConfiguration;

class ExecuteQuery extends DataTransferObject 
{
    
 
    /** @var string */
    public $suscriberId = "25";
    
     /** @var string */
    public $password = "";

     /** @var string */
    public $login = "";

    /** @var App\Http\DTO\Estafeta\Tracking\SearchType */
    public SearchType $searchType;

    /** @var App\Http\DTO\Estafeta\Tracking\SearchConfiguration */
    public SearchConfiguration $searchConfiguration ;


}
