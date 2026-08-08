<?php

namespace App\Domain\Network\Tenancy;

use App\Domain\Network\Tenancy\Models\TenantMembership;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class TenantAccessService
{
    public function __construct(private TenantContext $context) {}

    public function membership(?User $user = null): ?TenantMembership
    {
        if (! $user || ! $this->context->hasTenant()) return null;

        return TenantMembership::query()
            ->where('tenant_id', $this->context->id())
            ->where('user_id', $user->getKey())
            ->where('status', 'active')
            ->first();
    }

    public function hasMembership(?User $user = null): bool { return $this->membership($user) !== null; }
    public function role(?User $user = null): ?string { return $this->membership($user)?->role; }
    public function hasRole(string|array $roles, ?User $user = null): bool
    {
        return in_array($this->role($user), (array) $roles, true);
    }
    public function canManageTenant(?User $user = null): bool { return $this->hasRole(['owner', 'admin'], $user); }
    public function canManageMembers(?User $user = null): bool { return $this->canManageTenant($user); }

    public function updateMembership(TenantMembership $membership, array $attributes, ?User $actor = null, bool $networkOverride = false): void
    {
        abort_unless($this->context->hasTenant() && $membership->tenant_id === $this->context->id(), 404);
        if (! $networkOverride) {
            abort_unless($this->canManageMembers($actor), 403);
            if ($this->role($actor) === 'admin' && $membership->role === 'owner') abort(403);
        }

        $removesActiveOwner = $membership->role === 'owner'
            && $membership->status === 'active'
            && (($attributes['role'] ?? $membership->role) !== 'owner'
                || ($attributes['status'] ?? $membership->status) !== 'active');

        if ($removesActiveOwner && $membership->tenant->memberships()
            ->where('role', 'owner')->where('status', 'active')->count() <= 1) {
            throw ValidationException::withMessages(['role' => 'El tenant debe conservar al menos un owner activo.']);
        }

        $membership->update($attributes);
    }
}
