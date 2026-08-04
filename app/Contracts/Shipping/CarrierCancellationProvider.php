<?php
namespace App\Contracts\Shipping;
interface CarrierCancellationProvider{public function cancel(string $trackingNumber,string $idempotencyKey):array;}
