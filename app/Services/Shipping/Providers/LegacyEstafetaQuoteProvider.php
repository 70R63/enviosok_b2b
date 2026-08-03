<?php
namespace App\Services\Shipping\Providers;
use App\Contracts\Shipping\CarrierQuoteProvider;use App\Services\Shipping\Data\{UnifiedQuoteRequest,UnifiedQuoteResponse};use RuntimeException;
final class LegacyEstafetaQuoteProvider implements CarrierQuoteProvider {public function strategy():string{return'legacy_estafeta';}public function quote(UnifiedQuoteRequest $request):UnifiedQuoteResponse{throw new RuntimeException('Legacy requiere contexto comercial y no está habilitado en el adaptador técnico.');}}
