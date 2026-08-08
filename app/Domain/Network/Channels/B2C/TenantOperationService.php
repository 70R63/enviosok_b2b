<?php

namespace App\Domain\Network\Channels\B2C;

use App\Domain\Network\Billing\UsageService;
use App\Domain\Network\Channels\B2C\Models\TenantOperation;
use App\Domain\Network\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;

final class TenantOperationService
{
    public function __construct(private UsageService $usage) {}

    public function confirm(Tenant $tenant, TenantOperation $operation): TenantOperation
    {
        abort_unless((int) $operation->tenant_id === (int) $tenant->id, 404);
        return DB::transaction(function () use ($tenant, $operation): TenantOperation {
            $locked = TenantOperation::whereKey($operation->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'confirmed') $locked->update(['status' => 'confirmed']);
            $this->usage->record($tenant, 'operations', 1, 'tenant-operation:' . $locked->uuid, ['tenant_operation_uuid' => $locked->uuid]);
            return $locked->refresh();
        });
    }
}
