<?php

namespace App\Console\Commands;

use App\Domain\ApiHub\Models\TenantApiClient;
use App\Domain\Network\Providers\Models\ProviderConnection;
use App\Domain\Network\Providers\Models\ProviderIntegration;
use Illuminate\Console\Command;

final class BindProviderApiClient extends Command
{
    protected $signature = 'network:provider-api:bind {client_uuid} {connection_id}';

    protected $description = 'Trusted server provisioning; reuse an existing API Hub client/key';

    public function handle(): int
    {
        $client = TenantApiClient::where('uuid', $this->argument('client_uuid'))->firstOrFail();
        $connection = ProviderConnection::whereKey($this->argument('connection_id'))->where('provider_tenant_id', $client->tenant_id)->where('capability', 'LOCAL_SHIPPING')->firstOrFail();
        $existing = ProviderIntegration::where('api_client_id', $client->id)->first();
        if ($existing) {
            if ((int) $existing->provider_connection_id !== (int) $connection->id) {
                throw new \LogicException('Client already bound to another connection.');
            }$this->line($existing->uuid);

            return self::SUCCESS;
        }
        $i = ProviderIntegration::forceCreate(['api_client_id' => $client->id, 'provider_tenant_id' => $client->tenant_id, 'provider_connection_id' => $connection->id, 'status' => 'active', 'adapter' => 'disabled']);
        $this->line($i->uuid);

        return self::SUCCESS;
    }
}
