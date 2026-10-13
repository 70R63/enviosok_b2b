<?php

namespace App\Domain\Network\Providers;

use App\Domain\Network\Providers\Models\ProviderConnection;
use App\Models\B2cCotizacion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class TenantProviderQuoteSource
{
    public function __construct(private ProviderConsumerResolver $consumers, private ProviderConnectionService $connections,
        private LocalProviderQuoteEvaluator $evaluator) {}

    public function options(B2cCotizacion $quote, string $segment): array
    {
        try {
            if (! Schema::hasTable('network_provider_connections')) {
                return [];
            }
            $consumer = $this->consumers->current();
            if (! $consumer) {
                return [];
            }
            $connections = $this->connections->authorizedConnections($consumer, ProviderConnection::LOCAL_SHIPPING);
        } catch (Throwable $exception) {
            Log::warning('Tenant provider quote source unavailable', ['exception' => get_class($exception)]);

            return [];
        }
        $options = [];
        foreach ($connections as $connection) {
            try {
                $options = array_merge($options, $this->evaluator->options($consumer, $connection->provider, $quote, $segment));
            } catch (Throwable $exception) {
                Log::warning('Tenant provider quote unavailable', ['provider_tenant_id' => $connection->provider_tenant_id,
                    'exception' => get_class($exception)]);
            }
        }

        return $options;
    }
}
