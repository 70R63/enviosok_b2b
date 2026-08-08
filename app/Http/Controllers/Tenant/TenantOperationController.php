<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Network\Channels\B2C\Models\TenantOperation;
use App\Domain\Network\Channels\B2C\TenantOperationService;
use App\Domain\Network\Tenancy\TenantContext;
use App\Http\Controllers\Controller;

final class TenantOperationController extends Controller
{
    public function index(TenantContext $context)
    {
        $tenant = $context->tenant()->load('branding');
        return view('tenant.admin.operations', ['tenant' => $tenant, 'operations' => TenantOperation::where('tenant_id', $tenant->id)->latest()->paginate(30)]);
    }

    public function confirm(string $operation, TenantContext $context, TenantOperationService $service)
    {
        $item = TenantOperation::where('uuid', $operation)->firstOrFail();
        $service->confirm($context->tenant(), $item);
        return back()->with('success', 'Operación confirmada.');
    }
}
