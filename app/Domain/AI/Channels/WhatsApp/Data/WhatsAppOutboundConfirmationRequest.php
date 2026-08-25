<?php

namespace App\Domain\AI\Channels\WhatsApp\Data;

final readonly class WhatsAppOutboundConfirmationRequest
{
    public function __construct(
        public int $channelId,
        public string $recipient,
        public array $summary,
        public string $confirmToken,
        public string $cancelToken,
        public string $correlationKey,
    ) {}
}
