<?php
namespace App\Contracts\Shipping;
use App\Services\Shipping\Data\UnifiedQuoteRequest;
use App\Services\Shipping\Data\UnifiedQuoteResponse;
interface CarrierQuoteProvider { public function strategy():string; public function quote(UnifiedQuoteRequest $request):UnifiedQuoteResponse; }
