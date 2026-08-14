<?php

namespace App\Console\Commands;

use App\Domain\Network\Billing\EntitlementNormalizationService;
use Illuminate\Console\Command;

final class NormalizeNetworkEntitlements extends Command
{
    protected $signature = 'zigo:network:normalize-entitlements {--apply : Aplica la normalización; sin esta opción sólo informa}';
    protected $description = 'Normaliza de forma aditiva planes legacy y snapshots de entitlements canónicos';

    public function handle(EntitlementNormalizationService $normalizer): int
    {
        $apply = (bool) $this->option('apply');
        $this->line($apply ? 'MODE=APPLY' : 'MODE=DRY-RUN');
        $rows = $normalizer->run($apply);
        $this->table(['Scope', 'Tenant', 'Plan', 'Subscription', 'Missing', 'Action'], $rows);
        $changes = collect($rows)->whereIn('action', ['WOULD_ADD', 'ADDED'])->count();
        $this->info(($apply ? 'Cambios aplicados: ' : 'Cambios propuestos: ').$changes);

        return self::SUCCESS;
    }
}
