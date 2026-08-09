<?php

namespace App\Domain\Shipping\LastMile\Models;

use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Network\Tenancy\Models\TenantMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class DriverProfile extends Model
{
    public const STATUSES = ['ACTIVE', 'INACTIVE'];
    public const AVAILABILITY_STATUSES = ['AVAILABLE', 'BUSY', 'UNAVAILABLE'];

    protected $table = 'tenant_driver_profiles';

    protected $fillable = ['tenant_id', 'user_id', 'code', 'status', 'vehicle_label', 'availability_status', 'last_seen_at'];
    protected $casts = ['last_seen_at' => 'datetime'];

    protected static function booted(): void
    {
        self::creating(fn (self $profile) => $profile->uuid ??= (string) Str::uuid());
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DriverAssignment::class, 'driver_profile_id');
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'user_id', 'user_id');
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at !== null
            && $this->last_seen_at->gte(now()->subMinutes(max(1, (int) config('zigo_driver.presence_ttl_minutes', 5))));
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(DriverEarningEntry::class, 'driver_profile_id');
    }
}
