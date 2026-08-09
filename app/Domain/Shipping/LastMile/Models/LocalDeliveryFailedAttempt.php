<?php

namespace App\Domain\Shipping\LastMile\Models;

use App\Domain\Shipping\Local\Models\LocalShipment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class LocalDeliveryFailedAttempt extends Model
{
    public const REASONS = ['RECIPIENT_ABSENT', 'NO_ANSWER', 'ADDRESS_CLOSED', 'ADDRESS_NOT_FOUND', 'WRONG_ADDRESS', 'PROPERTY_APPEARS_ABANDONED', 'RECIPIENT_REFUSED', 'ACCESS_RESTRICTED', 'UNSAFE_LOCATION', 'OTHER'];
    public const REASON_LABELS = ['RECIPIENT_ABSENT' => 'Destinatario ausente', 'NO_ANSWER' => 'Nadie respondió', 'ADDRESS_CLOSED' => 'Domicilio cerrado', 'ADDRESS_NOT_FOUND' => 'Dirección no encontrada', 'WRONG_ADDRESS' => 'Dirección incorrecta', 'PROPERTY_APPEARS_ABANDONED' => 'Inmueble aparentemente abandonado', 'RECIPIENT_REFUSED' => 'Destinatario rechazó el paquete', 'ACCESS_RESTRICTED' => 'Acceso restringido', 'UNSAFE_LOCATION' => 'Lugar inseguro', 'OTHER' => 'Otro'];
    public $timestamps = false;
    protected $fillable = ['tenant_id', 'local_shipment_id', 'driver_assignment_id', 'driver_profile_id', 'attempt_number', 'reason', 'notes', 'photo_path', 'latitude', 'longitude', 'accuracy_meters', 'occurred_at', 'created_at'];
    protected $casts = ['latitude' => 'decimal:7', 'longitude' => 'decimal:7', 'accuracy_meters' => 'decimal:2', 'occurred_at' => 'datetime', 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        self::creating(fn (self $attempt) => $attempt->uuid ??= (string) Str::uuid());
        self::updating(fn () => throw new \LogicException('La evidencia de intento fallido es inmutable.'));
        self::deleting(fn () => throw new \LogicException('La evidencia de intento fallido es inmutable.'));
    }

    public function shipment(): BelongsTo { return $this->belongsTo(LocalShipment::class, 'local_shipment_id'); }
}
