<?php

namespace App\Domain\Network\Providers\Crm;

use App\Domain\Network\Providers\Models\ProviderIntegration;

final class ProviderCrmAdapterResolver
{
    public function resolve(ProviderIntegration $i): ProviderCrmAdapter
    {
        $class = config('provider_crm.adapters.'.$i->adapter);
        if (! $class) {
            return new DisabledProviderCrmAdapter;
        }
        $adapter = app($class);
        if (! $adapter instanceof ProviderCrmAdapter) {
            throw new \LogicException('Invalid adapter registration.');
        }

return $adapter;
    }
}
