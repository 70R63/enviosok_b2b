<?php
namespace App\Services\Shipping\Estafeta\Data;
final class EstafetaOperationResult {
 public function __construct(public readonly bool $success,public readonly string $classification,public readonly ?int $httpStatus,public readonly array $data,public readonly string $correlationId,public readonly int $durationMs,public readonly int $retryCount=0,public readonly ?string $providerCode=null) {}
 public function toArray(): array { return get_object_vars($this); }
}
