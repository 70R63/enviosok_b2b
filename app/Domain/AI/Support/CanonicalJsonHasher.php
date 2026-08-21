<?php

namespace App\Domain\AI\Support;

use JsonException;

final class CanonicalJsonHasher
{
    public function hash(array $value): string
    {
        StructuredDataGuard::validate($value);

        try {
            $json = json_encode($this->canonicalize($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        } catch (JsonException $exception) {
            throw new \InvalidArgumentException('Canonical AI data must be JSON serializable.', 0, $exception);
        }

        return hash('sha256', $json);
    }

    private function canonicalize(array $value): array
    {
        if (! array_is_list($value)) ksort($value, SORT_STRING);
        foreach ($value as $key => $item) if (is_array($item)) $value[$key] = $this->canonicalize($item);

        return $value;
    }
}
