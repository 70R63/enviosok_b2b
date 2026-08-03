<?php
namespace App\Services\Shipping\Data;
final class UnifiedQuoteResponse { public function __construct(public bool $success,public string $strategy,public string $httpProvider,public array $options=[],public ?string $correlationId=null,public ?string $errorCode=null,public bool $fallbackUsed=false,public array $metadata=[]){} public function toArray():array{return get_object_vars($this);} }
