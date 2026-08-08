<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Network\Tenancy\TenantAccessService;
use App\Domain\Network\Tenancy\TenantContext;
use App\Http\Controllers\Controller;

final class TenantAdminController extends Controller
{
    public function dashboard(TenantContext $context, TenantAccessService $access)
    {
        $tenant = $this->tenant($context);
        return view('tenant.admin.dashboard', ['tenant' => $tenant, 'membership' => $access->membership(auth()->user())]);
    }

    public function plan(TenantContext $context)
    {
        return view('tenant.admin.plan', ['tenant' => $this->tenant($context)]);
    }

    private function tenant(TenantContext $context)
    {
        return $context->tenant()->load(['branding', 'currentPlan.modules' => fn ($query) => $query->wherePivot('is_included', true)->orderBy('sort_order')]);
    }
}
