<?php
namespace App\Services\Shipping\Estafeta;
use App\Services\Shipping\Estafeta\Data\{EstafetaOperationRequest,EstafetaOperationResult};
class EstafetaTrackingClient { public function __construct(private EstafetaHttpClient $http){} public function track(EstafetaOperationRequest $r,string $token):EstafetaOperationResult{return $this->http->request('tracking','POST',(string)config('zigo_estafeta.tracking_url'),$r->require(['tracking_number'])->toArray(),$token);} }
