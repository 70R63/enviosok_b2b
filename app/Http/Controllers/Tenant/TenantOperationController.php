<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Network\Channels\B2C\Models\TenantOperation;
use App\Domain\Network\Channels\B2C\TenantOperationService;
use App\Domain\Network\Tenancy\Models\TenantMembership;
use App\Domain\Network\Tenancy\TenantAccessService;
use App\Domain\Network\Tenancy\TenantContext;
use App\Domain\Shipping\LastMile\Models\DriverProfile;
use App\Domain\Shipping\LastMile\Models\TenantDeliveryProofOption;
use App\Domain\Shipping\Local\LocalGuideService;
use App\Domain\Shipping\Local\LocalShipmentService;
use App\Domain\Shipping\Local\LocalTrackingService;
use App\Domain\Shipping\Local\Models\LocalShipment;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Schema;

final class TenantOperationController extends Controller
{
    public function index(Request $request, TenantContext $context)
    {
        $this->authorizeOperator($context);
        $tenant = $context->tenant()->load('branding');

        $query = TenantOperation::where('tenant_id', $tenant->id);
        if (Schema::hasTable('tenant_customer_checkouts')) $query->with('customerCheckout');
        if (Schema::hasTable('local_shipments')) $query->with('localShipment');
        $query->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status));
        $query->when($request->string('search')->toString(), fn ($q, $search) => $q->where(fn ($nested) => $nested->where('uuid', 'like', "%{$search}%")->orWhere('external_reference', 'like', "%{$search}%")));
        if (Schema::hasTable('tenant_customer_checkouts') && $request->filled('payment')) $query->whereHas('customerCheckout', fn ($q) => $q->where('payment_status', $request->string('payment')->toString()));
        return view('tenant.admin.operations', ['tenant' => $tenant, 'operations' => $query->latest()->paginate(30)->withQueryString()]);
    }

    public function confirm(string $operation, TenantContext $context, TenantOperationService $service)
    {
        $this->authorizeOperator($context);
        $item = TenantOperation::where('tenant_id', $context->tenant()->id)->where('uuid', $operation)->firstOrFail();
        $service->confirm($context->tenant(), $item);

        return back()->with('success', 'Operación confirmada.');
    }

    public function show(string $operation, TenantContext $context, TenantAccessService $access)
    {
        $this->authorizeOperator($context);
        $item = TenantOperation::where('tenant_id', $context->tenant()->id)->where('uuid', $operation)->firstOrFail();
        if (Schema::hasTable('tenant_customer_checkouts')) $item->load(['customerProfile.user','customerCheckout']);
        else $item->setRelation('customerCheckout', null);

        $relations = ['activeDriverAssignment.driverProfile.user', 'deliveryRequirement'];
        $podEnabled = Schema::hasTable('local_delivery_proofs');
        if ($podEnabled) $relations = array_merge($relations, ['deliveryProof', 'failedDeliveryAttempts']);
        $shipment = LocalShipment::with($relations)->where('tenant_id', $context->tenant()->id)->where('tenant_operation_id', $item->id)->first();

        return view('tenant.admin.operation', [
            'tenant' => $context->tenant()->load('branding'), 'operation' => $item, 'shipment' => $shipment,
            'canConfirm' => $item->status === 'quoted' && (! $item->customerCheckout || $item->customerCheckout->payment_status === 'APPROVED'),
            'canGenerateShipment' => $item->status === 'confirmed' && strtoupper((string) $item->provider) === 'ZIGO_LOCAL' && (! $item->customerCheckout || $item->customerCheckout->payment_status === 'APPROVED'),
            'drivers' => $shipment && $shipment->status === 'READY_FOR_PICKUP'
                ? app(\App\Domain\Shipping\LastMile\DriverDispatchService::class)->manualCandidates($context->tenant(), $shipment)
                : collect(),
            'canManageDrivers' => $access->hasRole(['owner', 'admin'], auth()->user()),
            'podEnabled' => $podEnabled,
            'proofOptions' => Schema::hasTable('tenant_delivery_proof_options') ? TenantDeliveryProofOption::where('tenant_id', $context->id())->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get() : collect(),
        ]);
    }

    public function tracking(string $operation, TenantContext $context)
    {
        $this->authorizeOperator($context);
        $item = TenantOperation::where('tenant_id', $context->id())
            ->where('uuid', $operation)
            ->firstOrFail();
        $shipment = LocalShipment::with('events')
            ->where('tenant_id', $context->id())
            ->where('tenant_operation_id', $item->id)
            ->firstOrFail();

        return view('tenant.admin.tracking', [
            'tenant' => $context->tenant()->load('branding'),
            'operation' => $item,
            'shipment' => $shipment,
        ]);
    }

    public function requestPickup(string $operation, TenantContext $context, LocalTrackingService $tracking, \App\Domain\Shipping\LastMile\DriverDispatchService $dispatch)
    {
        $this->authorizeOperator($context);
        $item = TenantOperation::where('tenant_id', $context->id())->where('uuid', $operation)->firstOrFail();
        $shipment = LocalShipment::where('tenant_id', $context->id())->where('tenant_operation_id', $item->id)->firstOrFail();
        if (! in_array($shipment->status, ['CREATED', 'READY_FOR_PICKUP'], true)) {
            throw ValidationException::withMessages(['pickup' => 'El envío ya no es elegible para solicitar recolección.']);
        }
        try {
            $tracking->transition($shipment, 'READY_FOR_PICKUP', auth()->id(), 'Recolección solicitada.');
            $dispatch->autoAssign($context->tenant(), $shipment->fresh(), auth()->id());
        } catch (\DomainException $exception) {
            throw ValidationException::withMessages(['pickup' => $exception->getMessage()]);
        }

        return back()->with('success', 'Recolección solicitada. Estamos asignando un repartidor.');
    }

    public function shipment(Request $request, string $operation, TenantContext $context, LocalShipmentService $shipments)
    {
        $this->authorizeOperator($context);
        $item = TenantOperation::where('tenant_id', $context->tenant()->id)->where('uuid', $operation)->firstOrFail();
        $checkout = Schema::hasTable('tenant_customer_checkouts') ? $item->customerCheckout()->first() : null;
        abort_unless($item->status === 'confirmed' && strtoupper((string) $item->provider) === 'ZIGO_LOCAL' && (! $checkout || $checkout->payment_status === 'APPROVED'), 422, 'La operación no está lista para generar guía.');
        $data = $request->validate([
            'sender.name' => 'required|max:120', 'sender.address' => 'required|max:300', 'sender.postal_code' => 'required|regex:/^\d{5}$/', 'sender.phone' => 'nullable|max:30',
            'recipient.name' => 'required|max:120', 'recipient.address' => 'required|max:300', 'recipient.postal_code' => 'required|regex:/^\d{5}$/', 'recipient.phone' => 'nullable|max:30',
            'package.type' => 'required|max:40', 'package.weight' => 'required|numeric|gt:0', 'package.length' => 'nullable|numeric|gt:0', 'package.width' => 'nullable|numeric|gt:0', 'package.height' => 'nullable|numeric|gt:0', 'reference' => 'nullable|max:100',
            'delivery_proof_option_uuid' => 'nullable|uuid',
        ]);
        $data['pricing'] = ['final_price' => (float) ($item->metadata['final_price'] ?? 0), 'currency' => 'MXN'];
        $shipment = $shipments->create($context->tenant(), $item, $data, auth()->id());

        return redirect()->route('tenant.admin.operations.show', $item->uuid)->with('success', 'Envío local creado: '.$shipment->tracking_number);
    }

    public function guide(string $operation, TenantContext $context, LocalGuideService $guides)
    {
        $this->authorizeOperator($context);
        $item = TenantOperation::where('tenant_id', $context->tenant()->id)->where('uuid', $operation)->firstOrFail();
        $shipment = LocalShipment::where('tenant_id', $context->tenant()->id)->where('tenant_operation_id', $item->id)->firstOrFail();
        $host = $context->tenant()->primaryDomain()->value('domain') ?: request()->getHost();

        return response($guides->pdf($shipment, $host), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$shipment->tracking_number.'.pdf"']);
    }

    private function authorizeOperator(TenantContext $context): void
    {
        abort_unless(TenantMembership::query()->where('tenant_id', $context->tenant()->id)->where('user_id', auth()->id())->where('status', 'active')->whereIn('role', ['owner', 'admin', 'operator'])->exists(), 403);
    }
}
