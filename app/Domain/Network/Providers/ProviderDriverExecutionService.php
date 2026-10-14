<?php

namespace App\Domain\Network\Providers;

use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Network\Tenancy\Models\TenantMembership;
use App\Domain\Shipping\LastMile\DeliveryEvidenceService;
use App\Domain\Shipping\LastMile\DeliveryRequirementService;
use App\Domain\Shipping\LastMile\Models\DriverAssignment;
use App\Domain\Shipping\LastMile\Models\DriverProfile;
use App\Domain\Shipping\LastMile\Models\LocalDeliveryFailedAttempt;
use App\Domain\Shipping\LastMile\Models\LocalDeliveryProof;
use App\Domain\Shipping\Local\LocalTrackingService;
use App\Domain\Shipping\Local\Models\LocalShipment;
use App\Domain\Shipping\Local\Models\LocalTrackingEvent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Provider authorization around the existing tracking/evidence engine. */
final class ProviderDriverExecutionService
{
    public function __construct(private LocalTrackingService $tracking, private DeliveryEvidenceService $evidence,
        private DeliveryRequirementService $requirements, private ProviderDispatchEligibility $eligibility) {}

    public function transition(DriverAssignment $assignment, int $actorId, string $status): LocalTrackingEvent
    {
        if (! in_array($status, ['PICKED_UP', 'IN_TRANSIT', 'OUT_FOR_DELIVERY'], true)) {
            throw ValidationException::withMessages(['status' => 'Acción de ejecución no válida.']);
        }

        return DB::transaction(function () use ($assignment, $actorId, $status) {
            $locked = $this->lockedAssignment($assignment, $actorId);
            if ($status === 'OUT_FOR_DELIVERY' && $locked->shipment->status === 'DELIVERY_FAILED'
                && $this->requirements->attemptsRemaining($locked->shipment) < 1) {
                throw ValidationException::withMessages(['status' => 'Límite de intentos alcanzado.']);
            }

            return $this->tracking->transition($locked->shipment, $status, $actorId);
        }, 5);
    }

    public function deliver(DriverAssignment $assignment, int $actorId, array $data, ?UploadedFile $photo = null,
        ?string $signature = null): LocalDeliveryProof
    {
        // No SQL retry around filesystem writes; a request retry is safe after rollback.
        return DB::transaction(function () use ($assignment, $actorId, $data, $photo, $signature) {
            $locked = $this->lockedAssignment($assignment, $actorId, true);
            $existing = LocalDeliveryProof::where('tenant_id', $locked->tenant_id)
                ->where('local_shipment_id', $locked->local_shipment_id)->where('driver_assignment_id', $locked->id)->first();
            if ($locked->shipment->status === 'DELIVERED') {
                abort_unless($existing && $locked->status === 'COMPLETED', 404);

                return $existing;
            }
            abort_unless($locked->shipment->status === 'OUT_FOR_DELIVERY' && ! $existing, 422);
            $requirement = $this->requirements->forShipment($locked->shipment);
            $validated = Validator::make(array_merge($data, ['photo' => $photo, 'signature' => $signature ? 'provided' : null]),
                $this->requirements->proofRules($requirement))->validate();
            if ($signature) {
                $image = @getimagesizefromstring($signature);
                if (strlen($signature) > 512000 || ! $image || $image['mime'] !== 'image/png' || $image[0] < 100 || $image[1] < 40) {
                    throw ValidationException::withMessages(['signature' => 'La firma no es válida.']);
                }
            }
            $this->requirements->assertEvidence($requirement, $validated, $photo, $signature);

            return $this->evidence->deliver($locked, $actorId, $validated, $photo, $signature);
        });
    }

    public function fail(DriverAssignment $assignment, int $actorId, array $data, ?UploadedFile $photo = null): LocalDeliveryFailedAttempt
    {
        return DB::transaction(function () use ($assignment, $actorId, $data, $photo) {
            $locked = $this->lockedAssignment($assignment, $actorId);
            if ($locked->shipment->status === 'DELIVERY_FAILED') {
                return LocalDeliveryFailedAttempt::where('tenant_id', $locked->tenant_id)
                    ->where('local_shipment_id', $locked->local_shipment_id)->where('driver_assignment_id', $locked->id)->latest('id')->firstOrFail();
            }
            abort_unless($locked->shipment->status === 'OUT_FOR_DELIVERY', 422);
            $validated = Validator::make(array_merge($data, ['photo' => $photo]), [
                'reason' => ['required', Rule::in(LocalDeliveryFailedAttempt::REASONS)],
                'notes' => ['nullable', 'required_if:reason,OTHER', 'string', 'max:500'],
                'photo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
                'latitude' => ['nullable', 'numeric', 'between:-90,90'], 'longitude' => ['nullable', 'numeric', 'between:-180,180'],
                'accuracy_meters' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            ])->validate();

            return $this->evidence->fail($locked, $actorId, $validated, $photo);
        });
    }

    private function lockedAssignment(DriverAssignment $assignment, int $actorId, bool $allowCompleted = false): DriverAssignment
    {
        $shipment = LocalShipment::whereKey($assignment->local_shipment_id)->lockForUpdate()->firstOrFail();
        $locked = DriverAssignment::whereKey($assignment->id)->lockForUpdate()->firstOrFail();
        $driver = DriverProfile::whereKey($locked->driver_profile_id)->lockForUpdate()->firstOrFail();
        $membership = TenantMembership::where('tenant_id', $shipment->tenant_id)->where('user_id', $actorId)->lockForUpdate()->first();
        abort_unless($shipment->operation?->source_type === 'provider_contract'
            && (int) $locked->local_shipment_id === (int) $shipment->id
            && (int) $shipment->tenant_id === (int) $locked->tenant_id && (int) $driver->tenant_id === (int) $shipment->tenant_id
            && (int) $driver->user_id === $actorId && $driver->status === 'ACTIVE'
            && $membership && $membership->role === 'driver' && $membership->status === 'active'
            && Tenant::whereKey($shipment->tenant_id)->where('status', 'active')->exists()
            && $this->eligibility->allows($shipment)
            && (($locked->status === 'ACTIVE' && (int) $locked->active_shipment_id === (int) $shipment->id)
                || ($allowCompleted && $locked->status === 'COMPLETED' && $shipment->status === 'DELIVERED')), 404);
        $locked->setRelation('shipment', $shipment);
        $locked->setRelation('driverProfile', $driver);

        return $locked;
    }
}
