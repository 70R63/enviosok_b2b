<?php
namespace App\Services\Shipping\Estafeta;
use App\Services\Shipping\Estafeta\Data\{EstafetaOperationRequest,EstafetaOperationResult};
class EstafetaGateway {
 public function __construct(private EstafetaConfigurationValidator $validator,private EstafetaTokenManager $tokens,private EstafetaCoverageClient $coverage,private EstafetaQuoteClient $quotes,private EstafetaShipmentClient $shipments,private EstafetaTrackingClient $tracking,private EstafetaCancellationClient $cancellations){}
 public function authenticate(bool $force=false):array{return['authenticated'=>$this->tokens->token($force)!=='','environment'=>config('zigo_estafeta.environment')];}
 public function checkCoverage(EstafetaOperationRequest $r):EstafetaOperationResult{return $this->withToken(fn($t)=>$this->coverage->check($r,$t));}
 public function quote(EstafetaOperationRequest $r):EstafetaOperationResult{return $this->withToken(fn($t)=>$this->quotes->quote($r,$t));}
 public function createShipment(EstafetaOperationRequest $r):EstafetaOperationResult{return $this->withToken(fn($t)=>$this->shipments->create($r,$t));}
 public function track(EstafetaOperationRequest $r):EstafetaOperationResult{return $this->withToken(fn($t)=>$this->tracking->track($r,$t));}
 public function cancelShipment(EstafetaOperationRequest $r):EstafetaOperationResult{return $this->withToken(fn($t)=>$this->cancellations->cancel($r,$t));}
 public function health():array{return$this->validator->validate();}
 private function withToken(callable $op):EstafetaOperationResult{$result=$op($this->tokens->token());if($result->httpStatus===401){$this->tokens->invalidate();$result=$op($this->tokens->token(true));}return$result;}
}
