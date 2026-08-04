<?php
namespace App\Services\Shipping\Xperta;
use App\Contracts\Shipping\CarrierCancellationProvider;use RuntimeException;
final class UnavailableXpertaCancellationProvider implements CarrierCancellationProvider{public function cancel(string $trackingNumber,string $idempotencyKey):array{throw new RuntimeException('La cancelación Xperta no está disponible porque su contrato no ha sido confirmado.');}}
