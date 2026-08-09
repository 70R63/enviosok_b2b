<?php

namespace App\Domain\Shipping\LastMile;

use App\Domain\Network\Billing\EntitlementService;
use App\Domain\Network\Billing\SubscriptionService;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Shipping\LastMile\Models\DriverAssignment;
use App\Domain\Shipping\LastMile\Models\DriverProfile;
use App\Domain\Shipping\Local\Models\LocalShipment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class DriverDispatchService
{
    private const OPERATIONAL_STATUSES = ['READY_FOR_PICKUP', 'PICKED_UP', 'IN_TRANSIT', 'OUT_FOR_DELIVERY', 'DELIVERY_FAILED'];

    public function __construct(private EntitlementService $entitlements, private SubscriptionService $subscriptions) {}

    public function candidates(Tenant $tenant, LocalShipment $shipment, array $criteria = []): Collection
    {
        if (! $this->dispatchUsable($tenant, $shipment)) {
            return collect();
        }

        $onlineSince = now()->subMinutes(max(1, (int) config('zigo_driver.presence_ttl_minutes', 5)));

        return $this->baseCandidateQuery($tenant)
            ->where('availability_status', 'AVAILABLE')
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '>=', $onlineSince)
            ->withCount(['assignments as operational_load' => fn (Builder $query) => $query
                ->where('status', 'ACTIVE')
                ->whereHas('shipment', fn (Builder $shipmentQuery) => $shipmentQuery->whereIn('status', self::OPERATIONAL_STATUSES))])
            ->orderBy('operational_load')
            ->orderBy('id')
            ->get();
    }

    public function manualCandidates(Tenant $tenant, LocalShipment $shipment): Collection
    {
        if ((int) $shipment->tenant_id !== (int) $tenant->id || ! $this->entitlements->has($tenant, 'DRIVER')) {
            return collect();
        }

        return $this->baseCandidateQuery($tenant)->orderBy('code')->get();
    }

    public function isManuallyEligible(Tenant $tenant, LocalShipment $shipment, DriverProfile $driver): bool
    {
        return $this->manualCandidates($tenant, $shipment)->contains(fn (DriverProfile $candidate) => $candidate->is($driver));
    }

    public function autoAssign(Tenant $tenant, LocalShipment $shipment, int $actorId): ?DriverAssignment
    {
        return DB::transaction(function () use ($tenant, $shipment, $actorId): ?DriverAssignment {
            $shipment = LocalShipment::query()->whereKey($shipment->id)->where('tenant_id', $tenant->id)->lockForUpdate()->firstOrFail();
            if ($shipment->status !== 'READY_FOR_PICKUP') {
                return null;
            }
            $existing = DriverAssignment::query()->where('active_shipment_id', $shipment->id)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }
            $driver = $this->candidates($tenant, $shipment)->first();
            if (! $driver) {
                return null;
            }

            $assignment = DriverAssignment::create([
                'tenant_id' => $tenant->id, 'local_shipment_id' => $shipment->id, 'active_shipment_id' => $shipment->id,
                'driver_profile_id' => $driver->id, 'assigned_by_user_id' => $actorId,
                'assigned_at' => now(), 'status' => 'ACTIVE',
            ]);
            $driver->forceFill(['availability_status' => 'BUSY'])->save();

            return $assignment;
        });
    }

    private function dispatchUsable(Tenant $tenant, LocalShipment $shipment): bool
    {
        return (int) $shipment->tenant_id === (int) $tenant->id
            && $shipment->status === 'READY_FOR_PICKUP'
            && $this->subscriptions->activeForTenant($tenant) !== null
            && $this->entitlements->has($tenant, 'DRIVER');
    }

    private function baseCandidateQuery(Tenant $tenant): Builder
    {
        return DriverProfile::query()->with('user')
            ->where('tenant_id', $tenant->id)
            ->where('status', 'ACTIVE')
            ->whereHas('membership', fn (Builder $query) => $query
                ->where('tenant_id', $tenant->id)->where('role', 'driver')->where('status', 'active'));
    }
}
