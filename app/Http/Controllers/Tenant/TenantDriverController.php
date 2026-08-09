<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Network\Tenancy\Models\TenantMembership;
use App\Domain\Network\Tenancy\TenantAccessService;
use App\Domain\Network\Tenancy\TenantContext;
use App\Domain\Shipping\LastMile\DriverAssignmentService;
use App\Domain\Shipping\LastMile\Models\DriverProfile;
use App\Domain\Shipping\LastMile\Models\DriverCompensationPolicy;
use App\Domain\Shipping\LastMile\Models\DriverDeliveryAttribution;
use App\Domain\Shipping\LastMile\Models\DriverEarningEntry;
use App\Domain\Shipping\Local\LocalTrackingService;
use App\Domain\Shipping\Local\Models\LocalShipment;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class TenantDriverController extends Controller
{
    public function index(TenantContext $context, TenantAccessService $access)
    {
        $this->authorizeView($access);
        $tenant = $context->tenant()->load('branding');

        return view('tenant.admin.drivers.index', [
            'tenant' => $tenant,
            'drivers' => DriverProfile::with('user')->where('tenant_id', $tenant->id)->orderBy('code')->get(),
            'memberships' => TenantMembership::with('user')->where('tenant_id', $tenant->id)->where('status', 'active')->whereNotIn('role', ['owner', 'admin'])->get(),
            'canManage' => $access->hasRole(['owner', 'admin'], auth()->user()),
        ]);
    }

    public function show(string $driver, TenantContext $context, TenantAccessService $access)
    {
        $this->authorizeView($access);
        $profile = DriverProfile::with(['user', 'assignments.shipment'])->where('tenant_id', $context->id())->where('uuid', $driver)->firstOrFail();
        $periodStart = now()->startOfMonth();
        $deliveries = DriverDeliveryAttribution::where('tenant_id', $context->id())->where('driver_profile_id', $profile->id)->where('delivered_at', '>=', $periodStart)->count();
        $failed = $profile->assignments()->whereHas('shipment.events', fn ($query) => $query->where('status', 'DELIVERY_FAILED')->where('occurred_at', '>=', $periodStart))->count();
        $generated = DriverEarningEntry::where('tenant_id', $context->id())->where('driver_profile_id', $profile->id)->where('entry_type', 'DELIVERY_EARNING')->where('occurred_at', '>=', $periodStart)->sum('amount');
        $paid = DriverEarningEntry::where('tenant_id', $context->id())->where('driver_profile_id', $profile->id)->where('entry_type', 'PAYOUT')->where('occurred_at', '>=', $periodStart)->sum('amount');

        return view('tenant.admin.drivers.show', [
            'tenant' => $context->tenant()->load('branding'), 'driver' => $profile,
            'policy' => DriverCompensationPolicy::where('tenant_id', $context->id())->where('driver_profile_id', $profile->id)->first(),
            'canManage' => $access->hasRole(['owner', 'admin'], auth()->user()),
            'report' => ['period' => $periodStart->format('m/Y'), 'deliveries' => $deliveries, 'failed' => $failed, 'generated' => $generated, 'paid' => $paid, 'pending' => (float) $generated - (float) $paid],
        ]);
    }

    public function dispatchQueue(TenantContext $context, TenantAccessService $access)
    {
        $this->authorizeView($access);
        $shipments = LocalShipment::query()
            ->with(['operation', 'activeDriverAssignment.driverProfile.user', 'events' => fn ($query) => $query->where('status', 'READY_FOR_PICKUP')])
            ->where('tenant_id', $context->id())
            ->where('status', 'READY_FOR_PICKUP')
            ->oldest('updated_at')
            ->paginate(30);

        return view('tenant.admin.drivers.dispatch', [
            'tenant' => $context->tenant()->load('branding'),
            'shipments' => $shipments,
            'canManage' => $access->hasRole(['owner', 'admin'], auth()->user()),
        ]);
    }

    public function store(Request $request, TenantContext $context, TenantAccessService $access)
    {
        $this->authorizeManage($access);
        $data = $request->validate(['membership_id' => ['required', 'integer'], 'code' => ['required', 'alpha_dash', 'max:40', Rule::unique('tenant_driver_profiles')->where('tenant_id', $context->id())], 'vehicle_label' => ['nullable', 'string', 'max:120']]);
        $membership = TenantMembership::where('tenant_id', $context->id())->where('status', 'active')->whereKey($data['membership_id'])->firstOrFail();
        abort_if(in_array($membership->role, ['owner', 'admin'], true), 422);
        $membership->update(['role' => 'driver']);
        DriverProfile::updateOrCreate(['tenant_id' => $context->id(), 'user_id' => $membership->user_id], ['code' => $data['code'], 'vehicle_label' => $data['vehicle_label'] ?? null, 'status' => 'ACTIVE']);

        return back()->with('success', 'Conductor habilitado.');
    }

    public function toggle(string $driver, TenantContext $context, TenantAccessService $access)
    {
        $this->authorizeManage($access);
        $profile = DriverProfile::where('tenant_id', $context->id())->where('uuid', $driver)->firstOrFail();
        $profile->update(['status' => $profile->status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE']);

        return back()->with('success', 'Estado del conductor actualizado.');
    }

    public function updateCompensation(Request $request, string $driver, TenantContext $context, TenantAccessService $access)
    {
        $this->authorizeManage($access);
        $profile = DriverProfile::where('tenant_id', $context->id())->where('uuid', $driver)->firstOrFail();
        $data = $request->validate([
            'compensation_type' => ['required', Rule::in(DriverCompensationPolicy::TYPES)],
            'settlement_frequency' => ['required', Rule::in(DriverCompensationPolicy::FREQUENCIES)],
            'amount_per_delivery' => ['nullable', 'required_unless:compensation_type,SALARIED', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
        ]);
        if ($data['compensation_type'] === 'SALARIED') {
            $data['amount_per_delivery'] = null;
        }
        $data['currency'] = strtoupper($data['currency']);
        $data['updated_by_user_id'] = auth()->id();
        DriverCompensationPolicy::updateOrCreate([
            'tenant_id' => $context->id(),
            'driver_profile_id' => $profile->id,
        ], $data);

        return back()->with('success', 'Política de compensación actualizada.');
    }

    public function assign(Request $request, string $shipment, TenantContext $context, TenantAccessService $access, DriverAssignmentService $assignments)
    {
        $this->authorizeManage($access);
        $data = $request->validate(['driver_uuid' => ['required', 'uuid']]);
        $localShipment = LocalShipment::where('tenant_id', $context->id())->where('uuid', $shipment)->firstOrFail();
        $driver = DriverProfile::where('tenant_id', $context->id())->where('uuid', $data['driver_uuid'])->firstOrFail();
        $assignments->assign($context->tenant(), $localShipment, $driver, auth()->id());

        return back()->with('success', 'Conductor asignado.');
    }

    public function transition(Request $request, string $shipment, TenantContext $context, TenantAccessService $access, LocalTrackingService $tracking)
    {
        $this->authorizeManage($access);
        $data = $request->validate(['status' => ['required', Rule::in(['CANCELED'])]]);
        $localShipment = LocalShipment::where('tenant_id', $context->id())->where('uuid', $shipment)->firstOrFail();
        try {
            $tracking->transition($localShipment, $data['status'], auth()->id());
        } catch (\DomainException $exception) {
            throw ValidationException::withMessages(['status' => $exception->getMessage()]);
        }

        return back()->with('success', 'Estado logístico actualizado.');
    }

    private function authorizeView(TenantAccessService $access): void
    {
        abort_unless($access->hasRole(['owner', 'admin', 'operator'], auth()->user()), 403);
    }

    private function authorizeManage(TenantAccessService $access): void
    {
        abort_unless($access->hasRole(['owner', 'admin'], auth()->user()), 403);
    }
}
