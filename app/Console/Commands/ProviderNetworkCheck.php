<?php

namespace App\Console\Commands;

use App\Domain\Network\Providers\ProviderNetworkReadinessService;
use App\Domain\Network\Tenancy\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

final class ProviderNetworkCheck extends Command
{
    protected $signature = 'network:provider-network:check {--consumer= : uuid:<UUID> or slug:<slug>} {--provider= : uuid:<UUID> or slug:<slug>} {--for-activation : Evaluate an inactive connection as an activation candidate}';

    protected $description = 'Read-only provider network readiness; never contacts an external service';

    public function handle(ProviderNetworkReadinessService $readiness): int
    {
        try {
            if (! Schema::hasTable('network_tenants')) {
                $this->error('FAIL: Esquema Network no disponible.');

                return self::FAILURE;
            }
            $report = $readiness->evaluate($this->tenant($this->option('consumer')), $this->tenant($this->option('provider')), null, (bool) $this->option('for-activation'));
            $this->table(['Estado', 'Check', 'Diagnóstico'], array_map(fn ($c) => [$c['status'], $c['code'], $c['message']], $report['checks']));

            return $report['ready'] ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable) {
            $this->error('FAIL: Diagnóstico no disponible. Revisa esquema/configuración de aplicación; no se modificaron datos.');

            return self::FAILURE;
        }
    }

    private function tenant(?string $identifier): ?Tenant
    {
        if (! is_string($identifier) || ! preg_match('/\A(uuid|slug):(.+)\z/', $identifier, $parts)) {
            throw new \InvalidArgumentException('Use an explicit uuid: or slug: identifier.');
        }

        return Tenant::where($parts[1], $parts[2])->first();
    }
}
