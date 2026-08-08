<?php

namespace App\Domain\Network\Channels\B2C\Models;

use App\Domain\Network\Billing\Models\Subscription;
use App\Domain\Network\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Domain\Shipping\Local\Models\LocalShipment;
use Illuminate\Support\Str;

final class TenantOperation extends Model
{
    public const STATUSES = ['draft', 'quoted', 'confirmed', 'canceled'];

    protected $table = 'network_tenant_operations';
    protected $fillable = ['tenant_id', 'subscription_id', 'channel', 'status', 'source_type', 'source_id', 'provider', 'service_code', 'external_reference', 'created_by_user_id', 'metadata'];
    protected $casts = ['metadata' => 'array'];

    protected static function booted(): void
    {
        static::creating(fn (self $operation) => $operation->uuid ??= (string) Str::uuid());
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function subscription(): BelongsTo { return $this->belongsTo(Subscription::class); }
    public function localShipment(): HasOne { return $this->hasOne(LocalShipment::class, 'tenant_operation_id'); }
}
