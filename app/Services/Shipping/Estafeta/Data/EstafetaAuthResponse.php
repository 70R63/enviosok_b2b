<?php
namespace App\Services\Shipping\Estafeta\Data;
final class EstafetaAuthResponse { public function __construct(public bool $success,public ?string $accessToken,public ?int $expiresIn,public ?string $tokenType,public ?string $providerCode,public ?string $message,public array $metadata=[]){} }
