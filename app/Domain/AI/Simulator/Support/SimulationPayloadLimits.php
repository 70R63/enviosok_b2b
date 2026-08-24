<?php

namespace App\Domain\AI\Simulator\Support;

use App\Domain\AI\Support\CanonicalJsonHasher;

final class SimulationPayloadLimits
{
    public function __construct(private CanonicalJsonHasher $hasher) {}

    public function assertMessage(string $message): void
    {
        if (strlen($message) > $this->limit('max_user_message_bytes', 8000)) {
            throw new \DomainException('simulation_message_too_large');
        }
    }

    public function assertFixture(array $fixture): void
    {
        if ($this->jsonBytes($fixture) > $this->limit('max_fixture_bytes', 32768)) {
            throw new \DomainException('simulation_fixture_too_large');
        }
    }

    public function appendTranscript(array $transcript, string $role, string $content): array
    {
        $candidate = [...$transcript, ['role' => $role, 'content' => $content]];
        if ($this->jsonBytes($candidate) > $this->limit('max_transcript_bytes', 65536)) {
            throw new \DomainException('simulation_transcript_too_large');
        }
        return $candidate;
    }

    public function jsonBytes(array $value): int
    {
        try {
            return $this->hasher->bytes($value);
        } catch (\InvalidArgumentException) {
            throw new \DomainException('simulation_payload_invalid');
        }
    }

    private function limit(string $key, int $default): int
    {
        return max(1, (int) config("ai.simulator.{$key}", $default));
    }
}
