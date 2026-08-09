<?php

namespace App\Domain\Network\Channels\B2C\Models;

use App\Domain\Network\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class TenantCustomerProfile extends Model
{
    public const STATUSES = ['active', 'suspended'];
    protected $fillable = ['tenant_id', 'user_id', 'status', 'display_name', 'phone'];

    protected static function booted(): void
    {
        self::creating(fn (self $profile) => $profile->uuid ??= (string) Str::uuid());
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function operations(): HasMany { return $this->hasMany(TenantOperation::class, 'customer_profile_id'); }
    public function checkouts(): HasMany { return $this->hasMany(TenantCustomerCheckout::class, 'customer_profile_id'); }
}
