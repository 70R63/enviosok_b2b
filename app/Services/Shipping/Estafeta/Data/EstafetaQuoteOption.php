<?php
namespace App\Services\Shipping\Estafeta\Data;
final class EstafetaQuoteOption { public function __construct(public string $serviceCode,public float $providerBasePrice,public string $currency='MXN',public ?float $taxes=null,public bool $extendedArea=false,public ?string $providerQuoteId=null,public ?string $providerCode=null,public ?string $message=null,public array $metadata=[]){} }
