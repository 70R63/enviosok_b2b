<?php
namespace App\Domain\AI\Support;
use InvalidArgumentException;
use JsonException;
final class StructuredDataGuard
{
    private const FORBIDDEN_KEYS=['secret','password','api_key','credential','credentials','token','access_token','refresh_token','client_secret','private_key'];
    private const PROMPT_KEYS=['prompt','system_prompt','developer_prompt','raw_prompt','master_prompt'];
    public static function validate(array$data):void
    {
        self::validateValue($data);
        try{json_encode($data,JSON_THROW_ON_ERROR);}catch(JsonException $e){throw new InvalidArgumentException('Structured AI data must be JSON serializable.',0,$e);}
    }
    private static function validateValue(mixed $value):void
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                if (is_string($key)) {
                    $normalized = self::normalizeKey($key);
                    $compact = str_replace('_', '', $normalized);
                    if (in_array($compact, array_map(static fn(string $item):string=>str_replace('_', '', $item), self::FORBIDDEN_KEYS), true)) {
                        throw new InvalidArgumentException('Sensitive keys are not allowed in AI structured data.');
                    }
                    if (in_array($compact, array_map(static fn(string $item):string=>str_replace('_', '', $item), self::PROMPT_KEYS), true)) {
                        throw new InvalidArgumentException('Monolithic prompt keys are not allowed in agent configuration.');
                    }
                }
                self::validateValue($item);
            }

            return;
        }

        if (is_object($value) || is_resource($value) || (! is_null($value) && ! is_scalar($value))) {
            throw new InvalidArgumentException('Structured AI data may contain only arrays, scalar values, and null.');
        }

        if (is_float($value) && (! is_finite($value))) {
            throw new InvalidArgumentException('Structured AI numeric values must be finite.');
        }
    }

    private static function normalizeKey(string $key):string
    {
        $key = preg_replace('/(?<!^)[A-Z]/', '_$0', trim($key)) ?? $key;
        $key = strtolower(preg_replace('/[-\s]+/', '_', $key) ?? $key);

        return trim(preg_replace('/_+/', '_', $key) ?? $key, '_');
    }
}
