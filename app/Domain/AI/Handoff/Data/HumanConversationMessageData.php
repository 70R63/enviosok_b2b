<?php

namespace App\Domain\AI\Handoff\Data;

final readonly class HumanConversationMessageData
{
    private function __construct(public string $message) {}

    public static function from(mixed $value): self
    {
        if (! is_string($value)) {
            throw new \InvalidArgumentException('Invalid human message.');
        }
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > 2000 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value)) {
            throw new \InvalidArgumentException('Invalid human message.');
        }

        return new self($value);
    }
}
