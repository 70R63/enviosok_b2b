<?php

namespace App\Domain\Network\Providers\Crm;

use App\Domain\Network\Providers\Models\ProviderIntegration;

final class DisabledProviderCrmAdapter implements ProviderCrmAdapter
{
    public function enabled(): bool
    {
        return false;
    }

    public function deliver(array $envelope, ProviderIntegration $integration): ?string
    {
        throw new \LogicException('Adapter disabled.');
    }
}
