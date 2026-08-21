<?php
namespace App\Domain\AI\Core;
use App\Domain\AI\Support\Exceptions\AiDisabledException;
final class AiFeatureGate
{
    public function enabled(): bool
    {
        return filter_var(config('ai.enabled', false), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }
    public function ensureEnabled(): void
    {
        if (! $this->enabled()) throw new AiDisabledException('AI Core is disabled.');
    }
}
