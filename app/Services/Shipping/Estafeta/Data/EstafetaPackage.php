<?php
namespace App\Services\Shipping\Estafeta\Data;
final class EstafetaPackage { public function __construct(public float $physicalWeight,public EstafetaDimensions $dimensions,public string $packageType='box',public int $quantity=1,public ?float $declaredValue=null){} public function volumetricWeight():float{return $this->dimensions->volumetricWeight();} }
