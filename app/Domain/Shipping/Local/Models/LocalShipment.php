<?php

namespace App\Domain\Shipping\Local\Models;

use App\Domain\Network\Channels\B2C\Models\TenantOperation;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Shipping\LastMile\Models\DriverAssignment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

final class LocalShipment extends Model
{
    public const STATUSES = ['CREATED', 'READY_FOR_PICKUP', 'PICKED_UP', 'IN_TRANSIT', 'OUT_FOR_DELIVERY', 'DELIVERED', 'DELIVERY_FAILED', 'CANCELED'];

    protected $fillable = ['tenant_id', 'tenant_operation_id', 'tracking_number', 'service_code', 'status', 'sender_snapshot', 'recipient_snapshot', 'package_snapshot', 'pricing_snapshot', 'guide_snapshot', 'created_by_user_id'];

    protected $casts = ['sender_snapshot' => 'array', 'recipient_snapshot' => 'array', 'package_snapshot' => 'array', 'pricing_snapshot' => 'array', 'guide_snapshot' => 'array'];

    protected static function booted(): void
    {
        self::creating(fn (self $s) => $s->uuid ??= (string) Str::uuid());
        self::updating(function (self $s): void {
            foreach (['uuid', 'tenant_id', 'tenant_operation_id', 'tracking_number', 'service_code', 'sender_snapshot', 'recipient_snapshot', 'package_snapshot', 'pricing_snapshot', 'guide_snapshot', 'created_by_user_id'] as $field) {
                if ($s->isDirty($field)) {
                    throw new \LogicException('El snapshot del envío es inmutable.');
                }
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(TenantOperation::class, 'tenant_operation_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(LocalTrackingEvent::class)->orderBy('occurred_at');
    }

    public function activeDriverAssignment(): HasOne
    {
        return $this->hasOne(DriverAssignment::class, 'active_shipment_id');
    }
}
