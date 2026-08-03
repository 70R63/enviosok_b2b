<?php
namespace App\Services\Shipping\Estafeta\Mappers;
use App\Services\Shipping\Estafeta\Data\EstafetaCoverageResponse;
final class EstafetaCoverageMapper { public function unsupported(?string $id=null):EstafetaCoverageResponse{return new EstafetaCoverageResponse(false,[],'CONTRACT_UNKNOWN','Contrato de cobertura no confirmado.',$id,['raw_classification'=>'contract_unknown']);} }
