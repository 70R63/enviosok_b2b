<?php
namespace App\Services\Shipping\Estafeta;
use App\Services\Shipping\Estafeta\Data\{EstafetaOperationRequest,EstafetaOperationResult};
class EstafetaShipmentClient { public function __construct(private EstafetaHttpClient $http){} public function create(EstafetaOperationRequest $r,string $token):EstafetaOperationResult{return $this->http->request('shipment','POST',(string)config('zigo_estafeta.shipment_url'),$r->toArray(),$token);} }
