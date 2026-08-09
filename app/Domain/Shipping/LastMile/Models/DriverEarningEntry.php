<?php

namespace App\Domain\Shipping\LastMile\Models;

use App\Domain\Shipping\Local\Models\LocalShipment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class DriverEarningEntry extends Model
{
    public const TYPES = ['DELIVERY_EARNING', 'ADJUSTMENT', 'PAYOUT'];
    public $timestamps = false;
    protected $fillable = ['tenant_id', 'driver_profile_id', 'driver_user_id', 'local_shipment_id', 'driver_assignment_id', 'compensation_policy_id', 'entry_type', 'compensation_type', 'amount', 'currency', 'occurred_at', 'created_at'];
    protected $casts = ['amount' => 'decimal:2', 'occurred_at' => 'datetime', 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        self::creating(fn (self $entry) => $entry->uuid ??= (string) Str::uuid());
        self::updating(fn () => throw new \LogicException('El ledger de ganancias es append-only.'));
        self::deleting(fn () => throw new \LogicException('El ledger de ganancias es append-only.'));
    }

    public function shipment(): BelongsTo { return $this->belongsTo(LocalShipment::class, 'local_shipment_id'); }
    public function assignment(): BelongsTo { return $this->belongsTo(DriverAssignment::class, 'driver_assignment_id'); }
    public function policy(): BelongsTo { return $this->belongsTo(DriverCompensationPolicy::class, 'compensation_policy_id'); }
}
