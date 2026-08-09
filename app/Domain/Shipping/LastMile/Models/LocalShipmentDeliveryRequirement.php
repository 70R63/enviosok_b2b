<?php

namespace App\Domain\Shipping\LastMile\Models;

use App\Domain\Shipping\Local\Models\LocalShipment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LocalShipmentDeliveryRequirement extends Model
{
    public $timestamps = false;
    protected $fillable = ['tenant_id', 'local_shipment_id', 'proof_option_code_snapshot', 'proof_option_name_snapshot', 'require_receiver_name', 'require_receiver_type', 'require_signature', 'require_photo', 'require_gps', 'receiver_policy', 'max_delivery_attempts', 'surcharge_amount_snapshot', 'currency_snapshot', 'created_at'];
    protected $casts = ['require_receiver_name' => 'boolean', 'require_receiver_type' => 'boolean', 'require_signature' => 'boolean', 'require_photo' => 'boolean', 'require_gps' => 'boolean', 'surcharge_amount_snapshot' => 'decimal:2'];

    protected static function booted(): void
    {
        self::updating(fn () => throw new \LogicException('Los requisitos de entrega son inmutables.'));
        self::deleting(fn () => throw new \LogicException('Los requisitos de entrega son inmutables.'));
    }

    public function shipment(): BelongsTo { return $this->belongsTo(LocalShipment::class, 'local_shipment_id'); }
}
