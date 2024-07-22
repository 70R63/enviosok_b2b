<?php

namespace App\Dto\Fedex;

use Spatie\DataTransferObject\DataTransferObject;
use Spatie\DataTransferObject\FieldValidator as Validator;

use App\Dto\Fedex\Shipper;
use App\Dto\Fedex\Recipients;
use App\Dto\Fedex\RequestedPackageLineItems;


class RequestedShipment extends DataTransferObject {

	public Shipper $shipper;

	public $recipients;

	public $shipDatestamp;

	//STANDARD_OVERNIGHT y FEDEX_EXPRESS_SAVER
	public $serviceType = "FEDEX_EXPRESS_SAVER";

	public $packagingType = "YOUR_PACKAGING";

	public $pickupType = "USE_SCHEDULED_PICKUP";

	public $blockInsightVisibility = false;

	public $shippingChargesPayment = array("paymentType" => "SENDER" );

	public $labelSpecification = array("imageType" => "PDF","labelStockType"=>"PAPER_85X11_TOP_HALF_LABEL");

	public $requestedPackageLineItems;

}