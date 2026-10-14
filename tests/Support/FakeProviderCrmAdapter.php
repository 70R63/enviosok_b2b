<?php

namespace Tests\Support;

use App\Domain\Network\Providers\Crm\ProviderCrmAdapter;
use App\Domain\Network\Providers\Models\ProviderIntegration;
use Illuminate\Support\Facades\DB;

final class FakeProviderCrmAdapter implements ProviderCrmAdapter
{
    public int $calls = 0;

    public int $failures = 0;

    public bool $insideTransaction = false;

    public function enabled(): bool
    {
        return true;
    }

    public function deliver(array $envelope, ProviderIntegration $integration): ?string
    {
        $this->calls++;
        $this->insideTransaction = DB::transactionLevel() > 0;
        if ($this->failures-- > 0) {
            throw new \RuntimeException('SECRET_REMOTE_CREDENTIAL');
        }

        return 'remote-'.$envelope['data']['fulfillment_uuid'];
    }
}
