<?php

namespace App\Domain\Shipping\Local;

use App\Domain\Shipping\Local\Models\LocalShipment;
use App\Domain\Shipping\Local\Models\LocalTrackingEvent;
use App\Domain\Network\Tenancy\Models\Tenant;
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

    public function read(Tenant $tenant, string $shipmentReference): array
    {
        $shipment = LocalShipment::query()->with('events')->where('tenant_id', $tenant->id)->where('uuid', $shipmentReference)->firstOrFail();
        $labels = ['CREATED'=>'Creado','READY_FOR_PICKUP'=>'Listo para recolectar','PICKED_UP'=>'Recolectado','IN_TRANSIT'=>'En tránsito','OUT_FOR_DELIVERY'=>'En reparto','DELIVERY_FAILED'=>'No entregado','DELIVERED'=>'Entregado','CANCELED'=>'Cancelado'];
        return [
            'shipment_reference' => $shipment->uuid,
            'tracking_reference' => $shipment->tracking_number,
            'status_code' => $shipment->status,
            'status_label' => $labels[$shipment->status] ?? 'Actualización',
            'delivered' => $shipment->status === 'DELIVERED',
            'events' => $shipment->events->map(fn (LocalTrackingEvent $event) => ['status_code'=>$event->status,'status_label'=>$labels[$event->status]??'Actualización','occurred_at'=>$event->occurred_at?->toIso8601String()])->values()->all(),
        ];
    }

    public function transition(LocalShipment $shipment, string $status, ?int $userId = null, ?string $description = null): LocalTrackingEvent
    {
        $status = strtoupper($status);

        return DB::transaction(function () use ($shipment, $status, $userId, $description) {
            $shipment = LocalShipment::query()->whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            if ($shipment->status === $status) {
                return $shipment->events()->where('status', $status)->latest('id')->firstOrFail();
            }
            if (! in_array($status, self::TRANSITIONS[$shipment->status] ?? [], true)) {
                throw new DomainException('Transición logística inválida.');
            }
            $shipment->update(['status' => $status]);

            return $shipment->events()->create(['status' => $status, 'event_code' => $status, 'description' => $description, 'occurred_at' => now(), 'created_by_user_id' => $userId]);
        });
    }
}
