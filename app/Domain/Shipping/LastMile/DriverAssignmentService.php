<?php

namespace App\Domain\Shipping\LastMile;

use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Shipping\LastMile\Models\DriverAssignment;
use App\Domain\Shipping\LastMile\Models\DriverProfile;
use App\Domain\Shipping\Local\Models\LocalShipment;
use Illuminate\Support\Facades\DB;

final class DriverAssignmentService
{
    public function __construct(private DriverDispatchService $dispatch) {}

    public function assign(Tenant $tenant, LocalShipment $shipment, DriverProfile $driver, int|\App\Domain\Network\Providers\ProviderApiPrincipal $actorId): DriverAssignment
    {
        if($actorId instanceof \App\Domain\Network\Providers\ProviderApiPrincipal) $actorId->authorize('provider:dispatch:write',$shipment);
        abort_unless((int) $shipment->tenant_id === (int) $tenant->id && (int) $driver->tenant_id === (int) $tenant->id, 404);
        abort_unless($this->dispatch->isManuallyEligible($tenant, $shipment, $driver), 422, 'El conductor no es elegible para esta asignación.');

        return DB::transaction(function () use ($tenant, $shipment, $driver, $actorId): DriverAssignment {
            $shipment = LocalShipment::query()->whereKey($shipment->id)->where('tenant_id', $tenant->id)->lockForUpdate()->firstOrFail();
            abort_unless($shipment->status === 'READY_FOR_PICKUP', 422, 'Sólo se pueden asignar recolecciones pendientes.');
            abort_unless(app(\App\Domain\Network\Providers\ProviderDispatchEligibility::class)->allows($shipment), 422, 'La solicitud aún no está aceptada.');
            $current = DriverAssignment::query()->where('active_shipment_id', $shipment->id)->lockForUpdate()->first();
            if ($shipment->operation?->source_type === 'provider_contract') {
                if($actorId instanceof \App\Domain\Network\Providers\ProviderApiPrincipal) $actorId->authorize('provider:dispatch:write',$shipment);
                else app(\App\Domain\Network\Providers\ProviderDispatchEligibility::class)->authorizeActor($tenant->id, $actorId);
                $driver = DriverProfile::whereKey($driver->id)->where('tenant_id', $tenant->id)->lockForUpdate()->firstOrFail();
                $membership = \App\Domain\Network\Tenancy\Models\TenantMembership::where('tenant_id', $tenant->id)
                    ->where('user_id', $driver->user_id)->lockForUpdate()->first();
                abort_unless($driver->status === 'ACTIVE' && $membership && $membership->role === 'driver' && $membership->status === 'active', 422, 'El conductor no está autorizado.');
                abort_unless(($current && (int) $current->driver_profile_id === (int) $driver->id)
                    || $driver->availability_status === 'AVAILABLE', 422, 'El conductor no está disponible.');
            }
            if ($current && (int) $current->driver_profile_id === (int) $driver->id) {
                return $current;
            }
            if ($current) {
                $current->update(['active_shipment_id' => null, 'status' => 'UNASSIGNED', 'unassigned_at' => now()]);
                $current->driverProfile()->whereDoesntHave('assignments', fn ($query) => $query->where('status', 'ACTIVE')->whereKeyNot($current->id))->update(['availability_status' => 'AVAILABLE']);
            }

            $assignmentData = [
                'tenant_id' => $tenant->id, 'local_shipment_id' => $shipment->id, 'active_shipment_id' => $shipment->id,
                'driver_profile_id' => $driver->id, 'assigned_by_user_id' => $actorId instanceof \App\Domain\Network\Providers\ProviderApiPrincipal ? null : $actorId,
                'assigned_at' => now(), 'status' => 'ACTIVE',
            ];
            if($actorId instanceof \App\Domain\Network\Providers\ProviderApiPrincipal) $assignmentData['actor_api_client_id']=$actorId->clientId();
            $assignment = $actorId instanceof \App\Domain\Network\Providers\ProviderApiPrincipal ? DriverAssignment::forceCreate($assignmentData) : DriverAssignment::create($assignmentData);
            $driver->forceFill(['availability_status' => 'BUSY'])->save();

            return $assignment;
        }, 5);
    }
}
