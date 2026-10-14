<?php

namespace App\Domain\Network\Providers;

use App\Domain\Network\Providers\Models\ProviderFulfillment;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Shipping\LastMile\Models\LocalDeliveryProof;
use App\Domain\Shipping\Local\Models\LocalShipment;
use Illuminate\Support\Facades\Schema;

/** A whitelist projection, never a serialized operational model. */
final class ProviderTrackingProjection
{
    private const LABELS = ['CREATED' => 'Envío preparado', 'READY_FOR_PICKUP' => 'Recolección solicitada',
        'PICKED_UP' => 'Paquete recolectado', 'IN_TRANSIT' => 'En tránsito', 'OUT_FOR_DELIVERY' => 'En reparto',
        'DELIVERED' => 'Entregado', 'DELIVERY_FAILED' => 'No fue posible completar la entrega'];

    public function project(ProviderFulfillment $bridge, Tenant $consumer): array
    {
        $shipment = $this->shipment($bridge, $consumer);
        $acceptance = $bridge->acceptanceStatus();
        $label = $acceptance !== 'ACCEPTED' || $shipment->status === 'READY_FOR_PICKUP'
            ? $bridge->consumerStatusLabel() : (self::LABELS[$shipment->status] ?? 'Estamos revisando tu envío');
        $timeline = $shipment->events()->orderBy('id')->get()->filter(fn ($e) => isset(self::LABELS[$e->status]))
            ->map(fn ($e) => ['label' => self::LABELS[$e->status], 'occurred_at' => $e->occurred_at->toIso8601String()])->values()->all();
        if ($acceptance === 'ACCEPTED') {
            // SQL timestamps have second precision: put acceptance before physical events on ties.
            array_splice($timeline, min(2, count($timeline)), 0, [['label' => 'Recolección confirmada',
                'occurred_at' => $bridge->decision->decided_at->toIso8601String()]]);
            usort($timeline, fn ($a, $b) => strcmp($a['occurred_at'], $b['occurred_at']));
        }
        $proof = $this->proof($bridge, $consumer);

        return ['status_label' => $label, 'timeline' => $timeline, 'pod' => $proof ? [
            'delivered_at' => $proof->captured_at->toIso8601String(), 'receiver' => $proof->received_by_name,
            'photo_available' => (bool) $proof->photo_path, 'signature_available' => (bool) $proof->signature_path,
        ] : null];
    }

    public function proof(ProviderFulfillment $bridge, Tenant $consumer): ?LocalDeliveryProof
    {
        $shipment = $this->shipment($bridge, $consumer);
        if ($bridge->acceptanceStatus() !== 'ACCEPTED' || $shipment->status !== 'DELIVERED' || ! Schema::hasTable('local_delivery_proofs')) {
            return null;
        }

        return LocalDeliveryProof::where('tenant_id', $bridge->provider_tenant_id)->where('local_shipment_id', $shipment->id)
            ->whereHas('assignment', fn ($q) => $q->where('tenant_id', $bridge->provider_tenant_id)
                ->where('local_shipment_id', $shipment->id)->where('status', 'COMPLETED')
                ->whereColumn('local_driver_assignments.driver_profile_id', 'local_delivery_proofs.driver_profile_id'))
            ->whereHas('driverProfile', fn ($q) => $q->where('tenant_id', $bridge->provider_tenant_id))->first();
    }

    private function shipment(ProviderFulfillment $bridge, Tenant $consumer): LocalShipment
    {
        abort_unless((int) $bridge->consumer_tenant_id === (int) $consumer->id, 404);

        return LocalShipment::whereKey($bridge->local_shipment_id)->where('tenant_id', $bridge->provider_tenant_id)
            ->where('tenant_operation_id', $bridge->tenant_operation_id)->firstOrFail();
    }
}
