<?php
namespace App\Services\Shipping\Xperta;
use RuntimeException;
final class XpertaProviderException extends RuntimeException {public function __construct(public readonly string $errorCode,public readonly array $diagnosticMetadata,?\Throwable $previous=null){parent::__construct($errorCode,0,$previous);}}
