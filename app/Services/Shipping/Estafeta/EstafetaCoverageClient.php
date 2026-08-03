<?php
namespace App\Services\Shipping\Estafeta;
use App\Services\Shipping\Estafeta\Data\{EstafetaOperationRequest,EstafetaOperationResult};
use Illuminate\Support\Str;
class EstafetaCoverageClient { public function __construct(private EstafetaHttpClient $http) {} public function check(EstafetaOperationRequest $request, string $token): EstafetaOperationResult { if(config('zigo_estafeta.contracts.coverage')!=='confirmed')return new EstafetaOperationResult(false,'contract_unknown',null,['message'=>'Contrato de cobertura no confirmado en legacy.'],(string)Str::uuid(),0,0,'CONTRACT_UNKNOWN');return $this->http->request('coverage', 'POST', (string) config('zigo_estafeta.coverage_url'), $request->require(['origin_postal_code','destination_postal_code'])->toArray(), $token); } }
