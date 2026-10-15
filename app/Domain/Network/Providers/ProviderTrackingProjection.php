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
        $safe = $this->publicProgress($bridge, $shipment);
        $label = $safe['status_label'];
        $timeline = $safe['timeline'];
        $proof = $this->proof($bridge, $consumer);

        return ['status_label' => $label, 'timeline' => $timeline, 'pod' => $proof ? [
            'delivered_at' => $proof->captured_at->toIso8601String(), 'receiver' => $proof->received_by_name,
            'photo_available' => (bool) $proof->photo_path, 'signature_available' => (bool) $proof->signature_path,
        ] : null];
    }

    /** Public endpoint contract: no proof queries, model serialization or private URLs. */
    public function publicProject(ProviderFulfillment $bridge, Tenant $consumer): array
    {
        $shipment = $this->shipment($bridge, $consumer);
        $identity = app(ProviderPresentation::class)->snapshotIdentity($shipment->guide_snapshot ?? []);
        return ['tracking_number' => $shipment->tracking_number, 'operator' => $identity['display_name'],
            'service' => data_get($shipment->guide_snapshot, 'service_name', $shipment->service_code),
            ...$this->publicProgress($bridge, $shipment)];
    }

    private function publicProgress(ProviderFulfillment $bridge, LocalShipment $shipment): array
    {
        $acceptance = $bridge->acceptanceStatus();
        $operator = app(ProviderPresentation::class)->snapshotIdentity($shipment->guide_snapshot ?? [])['display_name'];
        $label = $bridge->consumerStatusLabel();
        $timeline = [['label' => 'Compra confirmada', 'occurred_at' => $bridge->commercial_confirmed_at->toIso8601String()],
            ['label' => 'Solicitud enviada al operador logístico', 'occurred_at' => $bridge->pickup_requested_at->toIso8601String()]];
        if ($acceptance === 'ACCEPTED') {
            $label = 'Solicitud aceptada por '.$operator.' · recolección por confirmar';
            $timeline[] = ['label' => 'Solicitud aceptada por '.$operator, 'occurred_at' => $bridge->decision->decided_at->toIso8601String()];
            $confirmation = $shipment->events()->where('event_code', 'PICKUP_CONFIRMED')->first();
            if ($confirmation) {
                $data = $confirmation->metadata ?? [];
                $label = ($data['mode'] ?? null) === 'ASAP' ? $operator.' confirmó atención lo antes posible'
                    : 'Recolección confirmada: '.app(ProviderPickupPreference::class)->label($data);
                $timeline[] = ['label' => $label, 'occurred_at' => $confirmation->occurred_at->toIso8601String()];
            }
        }
        foreach ($shipment->events()->orderBy('id')->get() as $event) {
            if (! in_array($event->status, ['PICKED_UP', 'IN_TRANSIT', 'OUT_FOR_DELIVERY', 'DELIVERED', 'DELIVERY_FAILED'], true)
                || ! in_array($event->event_code, ['PICKED_UP', 'IN_TRANSIT', 'OUT_FOR_DELIVERY', 'DELIVERED', 'DELIVERY_FAILED'], true)) continue;
            $timeline[] = ['label' => self::LABELS[$event->status], 'occurred_at' => $event->occurred_at->toIso8601String()];
        }
        if ($acceptance === 'ACCEPTED' && ! in_array($shipment->status, ['CREATED', 'READY_FOR_PICKUP'], true)) {
            $label = self::LABELS[$shipment->status] ?? 'Estamos revisando tu envío';
        }
        // Stable ties retain commercial / acceptance / confirmation before physical events.
        usort($timeline, fn ($a, $b) => strcmp($a['occurred_at'], $b['occurred_at']));
        return ['status_label' => $label, 'timeline' => $timeline];
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
