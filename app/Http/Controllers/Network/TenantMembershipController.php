<?php

namespace App\Http\Controllers\Network;

use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Network\Tenancy\Models\TenantMembership;
use App\Domain\Network\Tenancy\TenantAccessService;
use App\Domain\Network\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Network\StoreTenantMembershipRequest;
use App\Http\Requests\Tenant\UpdateTenantMembershipRequest;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use App\Domain\Network\Operations\NetworkAdminAuditService;

final class TenantMembershipController extends Controller
{
    public function index(Tenant $tenant)
    {
        $memberships = $tenant->memberships()->with('user')->orderBy('role')->get();
        return view('network.tenants.members', compact('tenant', 'memberships'));
    }

    public function store(StoreTenantMembershipRequest $request, Tenant $tenant,NetworkAdminAuditService$audit)
    {
        $user = User::query()->where('email', $request->validated('email'))->first();
        if (! $user) throw ValidationException::withMessages(['email' => 'Usuario todavía no registrado.']);
        if ($tenant->memberships()->where('user_id', $user->id)->exists()) throw ValidationException::withMessages(['email' => 'El usuario ya pertenece a este tenant.']);
        if (! $tenant->memberships()->exists()
            && ($request->validated('role') !== 'owner' || $request->validated('status') !== 'active')) {
            throw ValidationException::withMessages(['role' => 'El primer usuario del tenant debe ser owner activo.']);
        }
        $membership=$tenant->memberships()->create(['user_id' => $user->id, 'role' => $request->validated('role'), 'status' => $request->validated('status')]);$audit->record($request->user()->id,'membership.created',$membership,[],$membership->toArray());
        return back()->with('success', 'Usuario agregado al tenant.');
    }

    public function update(UpdateTenantMembershipRequest $request, Tenant $tenant, TenantMembership $membership, TenantContext $context, TenantAccessService $access,NetworkAdminAuditService$audit)
    {
        abort_unless($membership->tenant_id === $tenant->id, 404);
        $before=$membership->only(['role','status']);$context->set($tenant);
        $access->updateMembership($membership, $request->validated(), $request->user(), true);
        $audit->record($request->user()->id,'membership.updated',$membership,$before,$membership->fresh()->only(['role','status']));
        return back()->with('success', 'Membership actualizado.');
    }
}
