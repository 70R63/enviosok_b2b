<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Network\Channels\B2C\Models\TenantOperation;
use App\Domain\Network\Channels\B2C\TenantOperationService;
use App\Domain\Network\Tenancy\Models\TenantMembership;
use App\Domain\Network\Tenancy\TenantContext;
use App\Domain\Shipping\Local\LocalGuideService;
use App\Domain\Shipping\Local\LocalShipmentService;
use App\Domain\Shipping\Local\Models\LocalShipment;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

final class TenantOperationController extends Controller
{
    public function index(TenantContext $context)
    {
        $this->authorizeOperator($context);
        $tenant = $context->tenant()->load('branding');

        return view('tenant.admin.operations', ['tenant' => $tenant, 'operations' => TenantOperation::where('tenant_id', $tenant->id)->latest()->paginate(30)]);
    }

    public function confirm(string $operation, TenantContext $context, TenantOperationService $service)
    {
        $this->authorizeOperator($context);
        $item = TenantOperation::where('tenant_id', $context->tenant()->id)->where('uuid', $operation)->firstOrFail();
        $service->confirm($context->tenant(), $item);

        return back()->with('success', 'Operación confirmada.');
    }

    public function show(string $operation, TenantContext $context)
    {
        $this->authorizeOperator($context);
        $item = TenantOperation::where('tenant_id', $context->tenant()->id)->where('uuid', $operation)->firstOrFail();

        return view('tenant.admin.operation', ['tenant' => $context->tenant()->load('branding'), 'operation' => $item, 'shipment' => LocalShipment::where('tenant_id', $context->tenant()->id)->where('tenant_operation_id', $item->id)->first()]);
    }

    public function shipment(Request $request, string $operation, TenantContext $context, LocalShipmentService $shipments)
    {
        $this->authorizeOperator($context);
        $item = TenantOperation::where('tenant_id', $context->tenant()->id)->where('uuid', $operation)->firstOrFail();
        $data = $request->validate([
            'sender.name' => 'required|max:120', 'sender.address' => 'required|max:300', 'sender.postal_code' => 'required|regex:/^\d{5}$/', 'sender.phone' => 'nullable|max:30',
            'recipient.name' => 'required|max:120', 'recipient.address' => 'required|max:300', 'recipient.postal_code' => 'required|regex:/^\d{5}$/', 'recipient.phone' => 'nullable|max:30',
            'package.type' => 'required|max:40', 'package.weight' => 'required|numeric|gt:0', 'package.length' => 'nullable|numeric|gt:0', 'package.width' => 'nullable|numeric|gt:0', 'package.height' => 'nullable|numeric|gt:0', 'reference' => 'nullable|max:100',
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
