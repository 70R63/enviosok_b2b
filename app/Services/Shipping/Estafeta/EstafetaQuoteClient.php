<?php
namespace App\Services\Shipping\Estafeta;
use App\Services\Shipping\Estafeta\Data\{EstafetaOperationRequest,EstafetaOperationResult};
use Illuminate\Support\Str;
class EstafetaQuoteClient { public function __construct(private EstafetaHttpClient $http){} public function quote(EstafetaOperationRequest $r,string $token):EstafetaOperationResult{if(config('zigo_estafeta.contracts.quote')!=='confirmed')return new EstafetaOperationResult(false,'contract_unknown',null,['message'=>'Contrato de cotización no confirmado en legacy.'],(string)Str::uuid(),0,0,'CONTRACT_UNKNOWN');return $this->http->request('quote','POST',(string)config('zigo_estafeta.quote_url'),$r->require(['origin_postal_code','destination_postal_code'])->toArray(),$token);} }
