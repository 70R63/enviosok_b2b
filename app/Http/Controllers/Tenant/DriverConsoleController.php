<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Shipping\LastMile\Models\DriverAssignment;
use App\Domain\Shipping\LastMile\Models\DriverCompensationPolicy;
use App\Domain\Shipping\LastMile\Models\DriverDeliveryAttribution;
use App\Domain\Shipping\LastMile\Models\DriverEarningEntry;
use App\Domain\Shipping\LastMile\DriverDeliveryService;
use App\Domain\Shipping\LastMile\DriverPresenceService;
use App\Domain\Shipping\Local\LocalTrackingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class DriverConsoleController extends Controller
{
    public function index(Request $request)
    {
        $profile = $request->attributes->get('driver_profile');
        $assignments = DriverAssignment::with('shipment')->where('tenant_id', $profile->tenant_id)->where('driver_profile_id', $profile->id)->where('status', 'ACTIVE')
            ->whereHas('shipment', fn ($query) => $query->whereIn('status', ['READY_FOR_PICKUP', 'PICKED_UP', 'IN_TRANSIT', 'OUT_FOR_DELIVERY', 'DELIVERY_FAILED']))->get();
        $tenant = $profile->tenant()->with('branding')->firstOrFail();
        $delivered = DriverDeliveryAttribution::where('tenant_id', $profile->tenant_id)->where('driver_profile_id', $profile->id)->count();
        $earnings = DriverEarningEntry::where('tenant_id', $profile->tenant_id)->where('driver_profile_id', $profile->id)->where('entry_type', 'DELIVERY_EARNING')->sum('amount');
        $completedDeliveries = DriverDeliveryAttribution::with('shipment')
            ->where('tenant_id', $profile->tenant_id)
            ->where('driver_profile_id', $profile->id)
            ->latest('delivered_at')
            ->limit(10)
            ->get();
        $completedEarnings = DriverEarningEntry::where('tenant_id', $profile->tenant_id)
            ->where('driver_profile_id', $profile->id)
            ->where('entry_type', 'DELIVERY_EARNING')
            ->whereIn('local_shipment_id', $completedDeliveries->pluck('local_shipment_id'))
            ->get()
            ->keyBy('local_shipment_id');
        $policy = DriverCompensationPolicy::where('tenant_id', $profile->tenant_id)
            ->where('driver_profile_id', $profile->id)
            ->first();

        return view('tenant.driver.dashboard', compact('tenant', 'profile', 'assignments', 'delivered', 'earnings', 'policy', 'completedDeliveries', 'completedEarnings'));
    }

    public function show(Request $request, string $shipment)
    {
        $assignment = $this->assignment($request, $shipment);
        $profile = $request->attributes->get('driver_profile');

        return view('tenant.driver.shipment', ['tenant' => $profile->tenant()->with('branding')->firstOrFail(), 'profile' => $profile, 'assignment' => $assignment]);
    }

    public function transition(Request $request, string $shipment, LocalTrackingService $tracking, DriverDeliveryService $delivery)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['PICKED_UP', 'IN_TRANSIT', 'OUT_FOR_DELIVERY', 'DELIVERED', 'DELIVERY_FAILED'])]]);
        $assignment = $this->assignment($request, $shipment);
        try {
            if ($data['status'] === 'DELIVERED') {
                $delivery->deliver($assignment, auth()->id());

                return redirect()->route('tenant.driver.dashboard')->with('success', 'Entrega completada correctamente.');
            } else {
                $tracking->transition($assignment->shipment, $data['status'], auth()->id());
            }
        } catch (\DomainException $exception) {
            throw ValidationException::withMessages(['status' => $exception->getMessage()]);
        }

        return back()->with('success', 'Estado actualizado.');
    }

    public function availability(Request $request, DriverPresenceService $presence)
    {
        $data = $request->validate(['availability_status' => ['required', Rule::in(['AVAILABLE', 'UNAVAILABLE'])]]);
        $profile = $request->attributes->get('driver_profile');
        $presence->setAvailability($profile, $data['availability_status']);

        return back()->with('success', 'Disponibilidad actualizada.');
    }

    private function assignment(Request $request, string $shipment): DriverAssignment
    {
        $profile = $request->attributes->get('driver_profile');

        return DriverAssignment::with('shipment')->where('tenant_id', $profile->tenant_id)->where('driver_profile_id', $profile->id)->where('status', 'ACTIVE')->whereHas('shipment', fn ($query) => $query->where('uuid', $shipment)->whereIn('status', ['READY_FOR_PICKUP', 'PICKED_UP', 'IN_TRANSIT', 'OUT_FOR_DELIVERY', 'DELIVERY_FAILED']))->firstOrFail();
    }
}
