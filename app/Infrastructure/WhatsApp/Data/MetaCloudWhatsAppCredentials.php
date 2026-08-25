<?php

namespace App\Infrastructure\WhatsApp\Data;

final readonly class MetaCloudWhatsAppCredentials
{
    public function __construct(
        public string $phoneNumberId,
        public string $accessToken,
    ) {}
}
