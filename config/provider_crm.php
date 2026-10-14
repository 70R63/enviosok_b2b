<?php

use App\Domain\Network\Providers\Crm\DisabledProviderCrmAdapter;

return ['adapters' => ['disabled' => DisabledProviderCrmAdapter::class],
    'lease_seconds' => 120, 'max_attempts' => 6];
