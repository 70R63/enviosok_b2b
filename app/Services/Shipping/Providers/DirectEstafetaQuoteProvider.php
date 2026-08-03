<?php
namespace App\Services\Shipping\Providers;
use App\Contracts\Shipping\CarrierQuoteProvider;use App\Services\Shipping\Data\{UnifiedQuoteRequest,UnifiedQuoteResponse};use RuntimeException;
final class DirectEstafetaQuoteProvider implements CarrierQuoteProvider {public function strategy():string{return'estafeta_direct';}public function quote(UnifiedQuoteRequest $request):UnifiedQuoteResponse{throw new RuntimeException('Contrato directo Estafeta quote unknown.');}}
