<?php

namespace App\Domain\Network\Onboarding\Services;

final class OnboardingMetadataSanitizer
{
    private const SENSITIVE_KEY = '/(?:password|passwd|token|secret|app[_-]?key|access[_-]?key|credential|authorization|cookie)/i';

    public function sanitize(array $metadata): array
    {
        return $this->walk($metadata);
    }

    private function walk(array $values): array
    {
        $sanitized = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && preg_match(self::SENSITIVE_KEY, $key)) {
                $sanitized[$key] = '[REDACTED]';
                continue;
            }

            $sanitized[$key] = is_array($value) ? $this->walk($value) : $value;
        }

        return $sanitized;
    }
}
