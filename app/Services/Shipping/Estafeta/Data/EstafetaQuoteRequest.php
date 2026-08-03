<?php
namespace App\Services\Shipping\Estafeta\Data;
final class EstafetaQuoteRequest { public function __construct(public string $originPostalCode,public string $destinationPostalCode,public EstafetaPackage $package,public ?string $serviceCode=null){} }
