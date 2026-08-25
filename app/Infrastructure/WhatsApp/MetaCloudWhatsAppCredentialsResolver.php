<?php

namespace App\Infrastructure\WhatsApp;

use App\Domain\AI\Channels\WhatsApp\Models\WhatsAppChannel;
use App\Infrastructure\WhatsApp\Data\MetaCloudWhatsAppCredentials;

final class MetaCloudWhatsAppCredentialsResolver
{
    public function resolve(int $channelId): MetaCloudWhatsAppCredentials
    {
        $channel = WhatsAppChannel::query()->findOrFail($channelId);

        return new MetaCloudWhatsAppCredentials($channel->phone_number_id, $channel->accessToken());
    }
}
