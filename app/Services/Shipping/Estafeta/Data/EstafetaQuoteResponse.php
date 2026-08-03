<?php
namespace App\Services\Shipping\Estafeta\Data;
final class EstafetaQuoteResponse { public function __construct(public bool $success,public array $options,public ?string $providerCode=null,public ?string $message=null,public ?string $correlationId=null,public array $metadata=[]){} }
