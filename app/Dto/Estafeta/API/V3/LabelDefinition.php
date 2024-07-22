<?php
namespace App\Dto\Estafeta\API\V3;

use Spatie\DataTransferObject\DataTransferObject;

use App\Dto\Estafeta\API\V3\WayBillDocument;
use App\Dto\Estafeta\API\V3\ItemDescription;
use App\Dto\Estafeta\API\V3\ServiceConfiguration;
use App\Dto\Estafeta\API\V3\LocationLabel;



class LabelDefinition extends DataTransferObject {
	public WayBillDocument $wayBillDocument;
    public ItemDescription $itemDescription;
    public ServiceConfiguration $serviceConfiguration;
    public LocationLabel $location;
}