<?php

namespace App\Domain\Network\Tenancy\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TenantMembership extends Model
{
    public const ROLES = ['owner', 'admin', 'operator', 'support', 'billing', 'viewer'];
    public const STATUSES = ['active', 'suspended'];

    protected $table = 'network_tenant_memberships';
    protected $fillable = ['tenant_id', 'user_id', 'role', 'status'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
