<?php
namespace App\Services\Shipping\Estafeta;
use App\Services\Shipping\Estafeta\Data\EstafetaOperationRequest;
/** Legacy adapter: preserves array-based callers while routing new operations through EstafetaGateway. */
class LegacyEstafetaAdapter {
 public function __construct(private EstafetaGateway $gateway){}
 public function cobertura(array $data):array{return$this->gateway->checkCoverage(EstafetaOperationRequest::fromArray($data))->toArray();}
 public function cotizar(array $data):array{return$this->gateway->quote(EstafetaOperationRequest::fromArray($data))->toArray();}
 public function crearGuia(array $data):array{return$this->gateway->createShipment(EstafetaOperationRequest::fromArray($data))->toArray();}
 public function rastrear(array $data):array{return$this->gateway->track(EstafetaOperationRequest::fromArray($data))->toArray();}
 public function cancelar(array $data):array{return$this->gateway->cancelShipment(EstafetaOperationRequest::fromArray($data))->toArray();}
}
