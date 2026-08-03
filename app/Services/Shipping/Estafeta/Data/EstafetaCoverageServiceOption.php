<?php
namespace App\Services\Shipping\Estafeta\Data;
final class EstafetaCoverageServiceOption { public function __construct(public string $serviceCode,public bool $covered,public bool $extendedArea=false,public ?string $providerCode=null,public ?string $message=null,public array $metadata=[]){} }
