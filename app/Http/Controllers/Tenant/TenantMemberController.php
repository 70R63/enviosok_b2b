<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Network\Tenancy\Models\TenantMembership;
use App\Domain\Network\Tenancy\TenantAccessService;
use App\Domain\Network\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateTenantMembershipRequest;

final class TenantMemberController extends Controller
{
    public function index(TenantContext $context)
    {
        $tenant = $context->tenant()->load('branding');
        $memberships = $tenant->memberships()->with('user')->orderByRaw("case when role = 'owner' then 0 else 1 end")->get();
        return view('tenant.admin.users.index', compact('tenant', 'memberships'));
    }

    public function update(UpdateTenantMembershipRequest $request, TenantMembership $membership, TenantAccessService $access)
    {
        $access->updateMembership($membership, $request->validated(), $request->user());
        return back()->with('success', 'Acceso del usuario actualizado.');
    }
}
