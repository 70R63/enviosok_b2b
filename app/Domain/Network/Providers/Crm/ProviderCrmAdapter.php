<?php

namespace App\Domain\Network\Providers\Crm;

use App\Domain\Network\Providers\Models\ProviderIntegration;

interface ProviderCrmAdapter
{
    public function enabled(): bool;

    /** Return optional external reference only after remote acknowledgement; event_id is stable across retries. */
    public function deliver(array $envelope, ProviderIntegration $integration): ?string;
}
