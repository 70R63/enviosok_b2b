<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Network\Billing\EntitlementService;
use App\Domain\Network\Providers\Models\ProviderFulfillment;
use App\Domain\Network\Providers\ProviderFulfillmentDecisionService;
use App\Domain\Network\Tenancy\TenantAccessService;
use App\Domain\Network\Tenancy\TenantContext;
use App\Domain\Shipping\LastMile\DriverDispatchService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class ProviderFulfillmentInboxController extends Controller
{
    public function index(Request $request, TenantContext $context, ProviderFulfillmentDecisionService $service)
    {
        $service->authorize($context->tenant(), $request->user());
        $data = $request->validate(['status' => ['nullable', Rule::in(['PENDING_PROVIDER', 'ACCEPTED', 'REJECTED'])]]);
        $query = ProviderFulfillment::where('provider_tenant_id', $context->id())->with(['shipment', 'decision']);
        if (($data['status'] ?? '') === 'PENDING_PROVIDER') {
            $query->whereDoesntHave('decision');
        } elseif ($data['status'] ?? null) {
            $query->whereHas('decision', fn ($q) => $q->where('status', $data['status']));
        }

        return response()->view('tenant.admin.provider-inbox', ['tenant' => $context->tenant()->load('branding'),
            'fulfillments' => $query->latest('id')->paginate(30)->withQueryString()])->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, string $fulfillment, TenantContext $context, ProviderFulfillmentDecisionService $service)
    {
        $service->authorize($context->tenant(), $request->user());
        $item = ProviderFulfillment::where('provider_tenant_id', $context->id())->where('uuid', $fulfillment)
            ->with(['shipment', 'decision'])->firstOrFail();
        $canDispatch = $item->acceptanceStatus() === 'ACCEPTED'
            && app(TenantAccessService::class)->hasRole(['owner', 'admin'], $request->user())
            && app(EntitlementService::class)->has($context->tenant(), 'DRIVER');
        $drivers = $canDispatch && $item->shipment->status === 'READY_FOR_PICKUP'
            ? app(DriverDispatchService::class)->manualCandidates($context->tenant(), $item->shipment)
            : collect();

        return response()->view('tenant.admin.provider-request', ['tenant' => $context->tenant()->load('branding'),
            'fulfillment' => $item, 'shipment' => $item->shipment, 'canDispatch' => $canDispatch,
            'drivers' => $drivers])->header('Cache-Control', 'private, no-store');
    }

    public function accept(Request $request, string $fulfillment, TenantContext $context, ProviderFulfillmentDecisionService $service)
    {
        $service->decide($context->tenant(), $fulfillment, $request->user(), 'ACCEPTED');

        return redirect()->route('tenant.admin.provider-inbox.show', $fulfillment)->with('success', 'Solicitud aceptada · recolección por confirmar.');
    }

    public function confirmPickup(Request $request, string $fulfillment, TenantContext $context)
    {
        $item = ProviderFulfillment::where('provider_tenant_id', $context->id())->where('uuid', $fulfillment)->firstOrFail();
        app(\App\Domain\Network\Providers\ProviderPickupConfirmationService::class)
            ->confirm($context->tenant(), $item, $request->user(), (array) $request->input('pickup_confirmation', []));
        return redirect()->route('tenant.admin.provider-inbox.show', $fulfillment)->with('success', 'Recolección confirmada.');
    }

    public function reject(Request $request, string $fulfillment, TenantContext $context, ProviderFulfillmentDecisionService $service)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $service->decide($context->tenant(), $fulfillment, $request->user(), 'REJECTED', $data['reason'] ?? null);

        return redirect()->route('tenant.admin.provider-inbox.show', $fulfillment)->with('success', 'Rechazo registrado para atención comercial.');
    }
}
