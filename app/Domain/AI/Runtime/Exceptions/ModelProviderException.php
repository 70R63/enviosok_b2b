<?php
namespace App\Domain\AI\Runtime\Exceptions;
use App\Domain\AI\Runtime\Data\ProviderFailureData;
class ModelProviderException extends \RuntimeException{public function __construct(string$message,public readonly ?ProviderFailureData$failure=null,?\Throwable$previous=null){parent::__construct($message,0,$previous);}}
