<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Network\Channels\B2C\Models\{TenantOperation, TenantCustomerCheckout};
use App\Domain\Network\Channels\B2C\Models\TenantCustomerAddress;
use App\Domain\Network\Tenancy\TenantContext;
use App\Domain\Shipping\LastMile\Models\TenantDeliveryProofOption;
use App\Domain\Shipping\Local\LocalGuideService;
use App\Domain\Shipping\Local\Models\LocalShipment;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

final class CustomerPortalController extends Controller
{
    public function dashboard(Request $request, TenantContext $context)
    {
        $profile = $request->attributes->get('customer_profile');
        $shipments = $this->shipments($context, $profile->id);
        $pendingQuery = $this->pendingCheckouts($context, $profile->id);
        return view('tenant.customer.dashboard', [
            'tenant' => $context->tenant()->load('branding'), 'profile' => $profile,
            'activeCount' => (clone $shipments)->whereNotIn('local_shipments.status', ['DELIVERED', 'CANCELED'])->count(),
            'deliveredCount' => (clone $shipments)->where('local_shipments.status', 'DELIVERED')->count(),
            'shipments' => $shipments->latest('local_shipments.created_at')->limit(5)->get(),
            'pendingCount' => $pendingQuery ? (clone $pendingQuery)->count() : 0,
            'pendingCheckouts' => $pendingQuery ? $pendingQuery->limit(5)->get() : collect(),
        ]);
    }

    public function index(Request $request, TenantContext $context)
    {
        $profile = $request->attributes->get('customer_profile');
        $filter = $request->query('estado', 'todos');
        $query = $this->shipments($context, $profile->id);
        if ($filter === 'activos') $query->whereNotIn('local_shipments.status', ['DELIVERED', 'CANCELED']);
        if ($filter === 'entregados') $query->where('local_shipments.status', 'DELIVERED');
        $pendingQuery = $this->pendingCheckouts($context, $profile->id);
        return view('tenant.customer.shipments', [
            'tenant' => $context->tenant()->load('branding'),
            'shipments' => $query->latest('local_shipments.created_at')->paginate(15, ['*'], 'envios'),
            'pendingCheckouts' => $pendingQuery ? $pendingQuery->paginate(10, ['*'], 'pendientes') : collect(),
            'filter' => $filter,
        ]);
    }

    public function show(Request $request, string $shipment, TenantContext $context)
    {
        $profile = $request->attributes->get('customer_profile');
        $item = $this->shipments($context, $profile->id)->with(['events', 'deliveryRequirement'])->where('local_shipments.uuid', $shipment)->firstOrFail();
        return view('tenant.customer.shipment', [
            'tenant' => $context->tenant()->load('branding'),
            'shipment' => $item,
            'pickupEnabled' => app(\App\Domain\Network\Billing\EntitlementService::class)->has($context->tenant(), 'DRIVER'),
        ]);
    }

    public function tracking(Request $request, string $shipment, TenantContext $context)
    {
        $profile = $request->attributes->get('customer_profile');
        $item = $this->shipments($context, $profile->id)
            ->with('events')
            ->where('local_shipments.uuid', $shipment)
            ->firstOrFail();

        return view('tenant.customer.tracking', [
            'tenant' => $context->tenant()->load('branding'),
            'shipment' => $item,
        ]);
    }

    public function guide(Request $request, string $shipment, TenantContext $context, LocalGuideService $guides)
    {
        $profile = $request->attributes->get('customer_profile');
        $item = $this->shipments($context, $profile->id)->where('local_shipments.uuid', $shipment)->firstOrFail();
        return response($guides->pdf($item, request()->getHost()), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$item->tracking_number.'.pdf"']);
    }

    public function profile(Request $request, TenantContext $context)
    {
        return view('tenant.customer.profile', ['tenant' => $context->tenant()->load('branding'), 'profile' => $request->attributes->get('customer_profile')]);
    }

    public function updateProfile(Request $request, TenantContext $context)
    {
        $data = $request->validate(['display_name' => ['required', 'string', 'max:160'], 'phone' => ['nullable', 'string', 'max:30']]);
        $request->attributes->get('customer_profile')->update($data);
        return back()->with('success', 'Tu perfil fue actualizado.');
    }

    public function quote(Request $request, TenantContext $context)
    {
        $profile = $request->attributes->get('customer_profile');
        $addresses = TenantCustomerAddress::where('tenant_id', $context->id())->where('customer_profile_id', $profile->id)->where('is_active', true)->orderBy('alias')->get();
        return view('tenant.b2c.quote', ['tenant' => $context->tenant()->load('branding'), 'result' => null, 'customerPortal' => true,
            'originAddresses' => $addresses->whereIn('address_type', ['origin','both'])->values(),
            'destinationAddresses' => $addresses->whereIn('address_type', ['destination','both'])->values(),
            'pickupEnabled' => app(\App\Domain\Network\Billing\EntitlementService::class)->has($context->tenant(), 'DRIVER'),
        ]);
    }

    public function support(TenantContext $context)
    {
        return view('tenant.customer.support', ['tenant' => $context->tenant()->load('branding')]);
    }

    public function proofOptions(TenantContext $context)
    {
        $options = Schema::hasTable('tenant_delivery_proof_options')
            ? TenantDeliveryProofOption::where('tenant_id', $context->id())->where('is_active', true)->orderBy('sort_order')->get() : collect();
        return view('tenant.customer.evidence', ['tenant' => $context->tenant()->load('branding'), 'options' => $options]);
    }

    private function shipments(TenantContext $context, int $profileId)
    {
        return LocalShipment::query()->select('local_shipments.*')
            ->join('network_tenant_operations as customer_operations', 'customer_operations.id', '=', 'local_shipments.tenant_operation_id')
            ->where('local_shipments.tenant_id', $context->id())
            ->where('customer_operations.tenant_id', $context->id())
            ->where('customer_operations.customer_profile_id', $profileId);
    }

    private function pendingCheckouts(TenantContext $context, int $profileId)
    {
        if (! Schema::hasTable('tenant_customer_checkouts')) return null;
        return TenantCustomerCheckout::query()
            ->where('tenant_id', $context->id())
            ->where('customer_profile_id', $profileId)
            ->whereIn('status', ['DRAFT', 'PENDING_PAYMENT'])
            ->latest('created_at');
    }
}
