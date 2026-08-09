<?php

namespace App\Domain\Shipping\LastMile;

use App\Domain\Shipping\LastMile\Models\DriverAssignment;
use App\Domain\Shipping\LastMile\Models\DriverCompensationPolicy;
use App\Domain\Shipping\LastMile\Models\DriverDeliveryAttribution;
use App\Domain\Shipping\LastMile\Models\DriverEarningEntry;
use App\Domain\Shipping\LastMile\Models\LocalDeliveryProof;
use App\Domain\Shipping\Local\LocalTrackingService;
use Illuminate\Support\Facades\DB;

final class DriverDeliveryService
{
    public function __construct(private LocalTrackingService $tracking) {}

    public function deliver(DriverAssignment $assignment, int $userId, ?int $proofId = null): DriverDeliveryAttribution
    {
        return DB::transaction(function () use ($assignment, $userId, $proofId): DriverDeliveryAttribution {
            $assignment = DriverAssignment::with(['shipment', 'driverProfile'])->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            abort_unless($assignment->status === 'ACTIVE' && (int) $assignment->driverProfile->user_id === $userId, 404);
            abort_unless($proofId && LocalDeliveryProof::whereKey($proofId)->where('tenant_id', $assignment->tenant_id)->where('local_shipment_id', $assignment->local_shipment_id)->where('driver_assignment_id', $assignment->id)->where('driver_profile_id', $assignment->driver_profile_id)->exists(), 422, 'Se requiere una prueba de entrega válida.');
            $event = $this->tracking->transition($assignment->shipment, 'DELIVERED', $userId);

            $attribution = DriverDeliveryAttribution::firstOrCreate(
                ['local_shipment_id' => $assignment->local_shipment_id],
                [
                    'tenant_id' => $assignment->tenant_id,
                    'driver_assignment_id' => $assignment->id,
                    'driver_profile_id' => $assignment->driver_profile_id,
                    'driver_user_id' => $userId,
                    'driver_code_snapshot' => $assignment->driverProfile->code,
                    'delivered_at' => $event->occurred_at,
                    'created_at' => now(),
                ]
            );

            $policy = DriverCompensationPolicy::where('tenant_id', $assignment->tenant_id)
                ->where('driver_profile_id', $assignment->driver_profile_id)
                ->first();
            if ($policy && in_array($policy->compensation_type, ['PER_DELIVERY', 'HYBRID'], true) && (float) $policy->amount_per_delivery > 0) {
                DriverEarningEntry::firstOrCreate(
                    [
                        'local_shipment_id' => $assignment->local_shipment_id,
                        'driver_profile_id' => $assignment->driver_profile_id,
                        'compensation_policy_id' => $policy->id,
                        'entry_type' => 'DELIVERY_EARNING',
                    ],
                    [
                        'tenant_id' => $assignment->tenant_id,
                        'driver_user_id' => $userId,
                        'driver_assignment_id' => $assignment->id,
                        'compensation_type' => $policy->compensation_type,
                        'amount' => $policy->amount_per_delivery,
                        'currency' => $policy->currency,
                        'occurred_at' => $event->occurred_at,
                        'created_at' => now(),
                    ]
                );
            }

            $assignment->update(['active_shipment_id' => null, 'status' => 'COMPLETED', 'unassigned_at' => now()]);
            $assignment->driverProfile->forceFill(['availability_status' => 'AVAILABLE'])->save();

            return $attribution;
        });
    }
}
