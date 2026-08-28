<?php

namespace App\Domain\AI\Conversations\Support;

use Carbon\CarbonImmutable;

final class ConversationTurnLease
{
    public function settings(): array
    {
        $providerTimeout = min(30, max(1, (int) config('ai.providers.openai.timeout', 30)));
        $lease = min(3600, max(60, (int) config('ai.conversation_turn_lease_seconds', 120)));
        $margin = min(300, max(5, (int) config('ai.conversation_turn_finalization_margin_seconds', 15)));

        if ($providerTimeout + $margin >= $lease) {
            throw new \LogicException('Conversation turn timing is configured unsafely.');
        }

        return [$providerTimeout, $margin, $lease];
    }

    public function newTokenHash(): string
    {
        return hash('sha256', random_bytes(32));
    }

    public function window(): array
    {
        [, , $lease] = $this->settings();
        $startedAt = CarbonImmutable::now();

        return [$startedAt, $startedAt->addSeconds($lease)];
    }

    public function renewedExpiry(): CarbonImmutable
    {
        [, , $lease] = $this->settings();

        return CarbonImmutable::now()->addSeconds($lease);
    }
}
