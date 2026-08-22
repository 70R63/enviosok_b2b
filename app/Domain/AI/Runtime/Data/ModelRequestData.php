<?php
namespace App\Domain\AI\Runtime\Data;
use App\Domain\AI\Runtime\Support\ProviderOutputSchemaGuard;
final readonly class ModelRequestData{public function __construct(public string$instructions,public array$input,public array$outputSchema,public int$maxOutputTokens,public string$reasoningEffort,public string$safetyIdentifier,public string$requestPurpose){if(trim($instructions)===''||$maxOutputTokens<1||$maxOutputTokens>600||$reasoningEffort!=='none'||!preg_match('/^[a-f0-9]{64}$/',$safetyIdentifier)||!preg_match('/^[a-z0-9_]{1,40}$/',$requestPurpose))throw new \InvalidArgumentException('Invalid model request.');ProviderOutputSchemaGuard::validate($outputSchema);}}
