<?php

namespace App\Domain\AI\Channels\WhatsApp\Support;

final class WhatsAppDeliveryStatusPolicy
{
    public function permits(string $current, string $incoming): bool
    {
        if ($current === $incoming) {
            return true;
        }

        return match ($current) {
            'processing' => in_array($incoming, ['sent', 'failed'], true),
            'sent' => in_array($incoming, ['delivered', 'read', 'failed'], true),
            'delivered' => $incoming === 'read',
            default => false,
        };
    }
}
