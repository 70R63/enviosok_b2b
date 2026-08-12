<?php

namespace App\Domain\Network\Tenancy\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Domain\Network\Catalog\Models\Plan;
use App\Domain\Network\Billing\Models\{Subscription,Entitlement,UsageEvent};
use App\Domain\Network\Channels\B2C\Models\TenantOperation;
use Illuminate\Support\Str;

class Tenant extends Model
{
    use HasFactory;

    public const STATUSES = ['active', 'inactive', 'suspended', 'archived'];

    protected $table = 'network_tenants';

    protected $fillable = ['name', 'slug', 'status', 'current_plan_id', 'archived_at', 'archive_reason'];

    protected $casts = ['current_plan_id' => 'integer', 'archived_at' => 'datetime'];

    /** Current commercial plan assignment; deliberately not a subscription. */
    public function currentPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'current_plan_id');
    }
    public function domains(): HasMany { return $this->hasMany(TenantDomain::class); }
    public function branding(): HasOne { return $this->hasOne(TenantBranding::class); }
    public function primaryDomain(): HasOne { return $this->hasOne(TenantDomain::class)->where('is_primary',true)->where('status','verified'); }
    public function memberships(): HasMany { return $this->hasMany(TenantMembership::class); }
    public function subscriptions(): HasMany { return $this->hasMany(Subscription::class); }
    public function entitlements(): HasMany { return $this->hasMany(Entitlement::class); }
    public function usageEvents(): HasMany { return $this->hasMany(UsageEvent::class); }
    public function operations(): HasMany { return $this->hasMany(TenantOperation::class); }
    public function customerProfiles(): HasMany { return $this->hasMany(\App\Domain\Network\Channels\B2C\Models\TenantCustomerProfile::class); }
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\User::class, 'network_tenant_memberships')
            ->withPivot(['role', 'status'])->withTimestamps();
    }

    protected static function booted(): void
    {
        static::creating(function (Tenant $tenant): void {
            $tenant->uuid ??= (string) Str::uuid();
        });
    }

    public function setSlugAttribute(string $value): void
    {
        $this->attributes['slug'] = Str::slug(Str::lower($value));
    }
}
