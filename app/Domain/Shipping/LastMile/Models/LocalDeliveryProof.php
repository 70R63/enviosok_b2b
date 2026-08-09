<?php

namespace App\Domain\Shipping\LastMile\Models;

use App\Domain\Shipping\Local\Models\LocalShipment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class LocalDeliveryProof extends Model
{
    public const RECEIVER_TYPES = ['RECIPIENT', 'FAMILY', 'RECEPTION', 'SECURITY', 'AUTHORIZED_OTHER'];
    public $timestamps = false;
    protected $fillable = ['tenant_id', 'local_shipment_id', 'driver_assignment_id', 'driver_profile_id', 'received_by_name', 'receiver_type', 'receiver_notes', 'photo_path', 'signature_path', 'latitude', 'longitude', 'accuracy_meters', 'captured_at', 'created_at'];
    protected $casts = ['latitude' => 'decimal:7', 'longitude' => 'decimal:7', 'accuracy_meters' => 'decimal:2', 'captured_at' => 'datetime', 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        self::creating(fn (self $proof) => $proof->uuid ??= (string) Str::uuid());
        self::updating(fn () => throw new \LogicException('La prueba de entrega es inmutable.'));
        self::deleting(fn () => throw new \LogicException('La prueba de entrega es inmutable.'));
    }

    public function shipment(): BelongsTo { return $this->belongsTo(LocalShipment::class, 'local_shipment_id'); }
    public function assignment(): BelongsTo { return $this->belongsTo(DriverAssignment::class, 'driver_assignment_id'); }
    public function driverProfile(): BelongsTo { return $this->belongsTo(DriverProfile::class, 'driver_profile_id'); }
}
