<?php
namespace App\Services\Shipping\Estafeta;
use App\Services\Shipping\Estafeta\Data\{EstafetaOperationRequest,EstafetaOperationResult};
class EstafetaCancellationClient { public function __construct(private EstafetaHttpClient $http){} public function cancel(EstafetaOperationRequest $r,string $token):EstafetaOperationResult{return $this->http->request('cancellation','POST',(string)config('zigo_estafeta.cancellation_url'),$r->require(['tracking_number'])->toArray(),$token);} }
