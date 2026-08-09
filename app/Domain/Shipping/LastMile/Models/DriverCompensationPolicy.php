<?php

namespace App\Domain\Shipping\LastMile\Models;

use App\Domain\Network\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class DriverCompensationPolicy extends Model
{
    public const TYPES = ['PER_DELIVERY', 'SALARIED', 'HYBRID'];
    public const FREQUENCIES = ['WEEKLY', 'BIWEEKLY', 'MONTHLY'];

    protected $fillable = ['tenant_id', 'driver_profile_id', 'compensation_type', 'settlement_frequency', 'amount_per_delivery', 'currency', 'updated_by_user_id'];
    protected $casts = ['amount_per_delivery' => 'decimal:2'];

    protected static function booted(): void
    {
        self::creating(fn (self $policy) => $policy->uuid ??= (string) Str::uuid());
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function driverProfile(): BelongsTo { return $this->belongsTo(DriverProfile::class, 'driver_profile_id'); }
    public function updatedBy(): BelongsTo { return $this->belongsTo(User::class, 'updated_by_user_id'); }
}
