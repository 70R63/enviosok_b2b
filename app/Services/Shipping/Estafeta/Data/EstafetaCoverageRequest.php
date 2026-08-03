<?php
namespace App\Services\Shipping\Estafeta\Data;
final class EstafetaCoverageRequest { public function __construct(public string $originPostalCode,public string $destinationPostalCode,public ?string $serviceCode=null){} }
