<?php
namespace App\Services\Shipping\Estafeta\Mappers;
use App\Services\Shipping\Estafeta\Data\EstafetaQuoteResponse;
final class EstafetaQuoteMapper { public function unsupported(?string $id=null):EstafetaQuoteResponse{return new EstafetaQuoteResponse(false,[],'CONTRACT_UNKNOWN','Contrato de cotización no confirmado.',$id,['raw_classification'=>'contract_unknown']);} }
