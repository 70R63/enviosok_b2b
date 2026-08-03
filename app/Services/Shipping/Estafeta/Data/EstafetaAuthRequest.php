<?php
namespace App\Services\Shipping\Estafeta\Data;
final class EstafetaAuthRequest { public function __construct(public string $clientId,public string $clientSecret,public string $scope,public string $grantType='client_credentials'){} public function toForm():array{return['grant_type'=>$this->grantType,'client_id'=>$this->clientId,'client_secret'=>$this->clientSecret,'scope'=>$this->scope];}}
