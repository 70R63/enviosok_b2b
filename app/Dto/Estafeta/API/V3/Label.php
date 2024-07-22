<?php
namespace App\Dto\Estafeta\API\V3;

use Spatie\DataTransferObject\DataTransferObject;

use App\Dto\Estafeta\API\V3\Identification;
use App\Dto\Estafeta\API\V3\SystemInformation;
use App\Dto\Estafeta\API\V3\LabelDefinition;

class Label extends DataTransferObject 
{
    public Identification $identification;
    public SystemInformation $systemInformation;
    public LabelDefinition $labelDefinition;
}
