<?php
namespace App\Services\Shipping\Data;
final class UnifiedQuoteRequest { public function __construct(public string $carrier,public string $originPostalCode,public string $destinationPostalCode,public float $weight,public float $length=1,public float $width=1,public float $height=1,public string $packageType='box',public ?string $serviceCode=null,public float $declaredValue=0){} public function toArray():array{return get_object_vars($this);} }
