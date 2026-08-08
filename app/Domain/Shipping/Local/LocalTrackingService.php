<?php

namespace App\Domain\Shipping\Local;

use App\Domain\Shipping\Local\Models\LocalShipment;
use App\Domain\Shipping\Local\Models\LocalTrackingEvent;
use DomainException;
use Illuminate\Support\Facades\DB;

final class LocalTrackingService
{
    private const TRANSITIONS = [
        'CREATED' => ['READY_FOR_PICKUP', 'CANCELED'], 'READY_FOR_PICKUP' => ['PICKED_UP', 'CANCELED'],
        'PICKED_UP' => ['IN_TRANSIT'], 'IN_TRANSIT' => ['OUT_FOR_DELIVERY'],
        'OUT_FOR_DELIVERY' => ['DELIVERED', 'DELIVERY_FAILED'], 'DELIVERY_FAILED' => ['OUT_FOR_DELIVERY', 'CANCELED'],
        'DELIVERED' => [], 'CANCELED' => [],
    ];

    public function transition(LocalShipment $shipment, string $status, ?int $userId = null, ?string $description = null): LocalTrackingEvent
    {
        $status = strtoupper($status);
        if (! in_array($status, self::TRANSITIONS[$shipment->status] ?? [], true)) throw new DomainException('Transición logística inválida.');
        return DB::transaction(function () use ($shipment, $status, $userId, $description) {
            $shipment->update(['status' => $status]);
            return $shipment->events()->create(['status' => $status, 'event_code' => $status, 'description' => $description, 'occurred_at' => now(), 'created_by_user_id' => $userId]);
        });
    }
}
