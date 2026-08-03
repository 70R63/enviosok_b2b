<?php
namespace App\Services\Shipping\Estafeta\Data;
use App\Services\Shipping\Estafeta\Exceptions\EstafetaValidationException;
final class EstafetaOperationRequest {
 public function __construct(private array $attributes) {}
 public static function fromArray(array $attributes): self { return new self($attributes); }
 public function toArray(): array { return $this->attributes; }
 public function require(array $keys): self { $missing=array_values(array_filter($keys,fn($key)=>blank($this->attributes[$key]??null))); if($missing) throw new EstafetaValidationException('Faltan campos requeridos: '.implode(', ',$missing)); return $this; }
}
