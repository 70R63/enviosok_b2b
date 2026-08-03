<?php
namespace App\Services\Shipping\Estafeta\Data;
final class EstafetaDimensions { public function __construct(public float $length,public float $width,public float $height){} public function volumetricWeight(float $factor=5000):float{return round(($this->length*$this->width*$this->height)/$factor,2);} }
