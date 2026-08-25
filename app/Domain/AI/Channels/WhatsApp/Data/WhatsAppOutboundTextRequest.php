<?php

namespace App\Domain\AI\Channels\WhatsApp\Data;

final readonly class WhatsAppOutboundTextRequest
{
    public function __construct(
        public int $channelId,
        public string $recipient,
        public string $body,
        public string $correlationKey,
    ) {}
}
