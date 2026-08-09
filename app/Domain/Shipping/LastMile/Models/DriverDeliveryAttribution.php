<?php

namespace App\Domain\Shipping\LastMile\Models;

use App\Domain\Shipping\Local\Models\LocalShipment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class DriverDeliveryAttribution extends Model
{
    public $timestamps = false;
    protected $fillable = ['tenant_id', 'local_shipment_id', 'driver_assignment_id', 'driver_profile_id', 'driver_user_id', 'driver_code_snapshot', 'delivered_at', 'created_at'];
    protected $casts = ['delivered_at' => 'datetime', 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        self::creating(fn (self $entry) => $entry->uuid ??= (string) Str::uuid());
        self::updating(fn () => throw new \LogicException('La atribución de entrega es inmutable.'));
        self::deleting(fn () => throw new \LogicException('La atribución de entrega es inmutable.'));
    }

    public function shipment(): BelongsTo { return $this->belongsTo(LocalShipment::class, 'local_shipment_id'); }
    public function assignment(): BelongsTo { return $this->belongsTo(DriverAssignment::class, 'driver_assignment_id'); }
    public function driverProfile(): BelongsTo { return $this->belongsTo(DriverProfile::class, 'driver_profile_id'); }
    public function driverUser(): BelongsTo { return $this->belongsTo(User::class, 'driver_user_id'); }
}
