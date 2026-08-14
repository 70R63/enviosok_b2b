<?php

namespace App\Domain\Network\Channels\B2C\Models;

use App\Domain\Network\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class TenantCustomerAddress extends Model
{
    public const TYPES = ['origin', 'destination', 'both'];

    protected $fillable = [
        'tenant_id','customer_profile_id','address_type','alias','contact_name','company','phone','email',
        'street','exterior','interior','postal_code','settlement','municipality','state','references',
        'is_default_origin','is_default_destination','is_active',
    ];

    protected $casts = ['is_default_origin'=>'boolean','is_default_destination'=>'boolean','is_active'=>'boolean'];

    protected static function booted(): void
    {
        self::creating(fn (self $address) => $address->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string { return 'uuid'; }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function customerProfile(): BelongsTo { return $this->belongsTo(TenantCustomerProfile::class, 'customer_profile_id'); }
}
