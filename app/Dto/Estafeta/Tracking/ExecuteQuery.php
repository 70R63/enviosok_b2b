<?php
namespace App\Http\DTO\Estafeta\Tracking;

use Spatie\DataTransferObject\DataTransferObject;
use Spatie\DataTransferObject\FieldValidator as Validator;


use App\Http\DTO\Estafeta\Tracking\SearchType;
use App\Http\DTO\Estafeta\Tracking\SearchConfiguration;

class ExecuteQuery extends DataTransferObject 
{
    
 
    /** @var string */
    public $suscriberId = "25";
    
     /** @var string */
    public $password = "1GCvGIu$";

     /** @var string */
    public $login = "Usuario1";

    /** @var App\Http\DTO\Estafeta\Tracking\SearchType */
    public SearchType $searchType;

    /** @var App\Http\DTO\Estafeta\Tracking\SearchConfiguration */
    public SearchConfiguration $searchConfiguration ;


}