<?php

namespace App\Http\Controllers\Network;

use App\Domain\Network\Providers\Models\ProviderPayable;
use App\Domain\Network\Providers\ProviderSettlementService;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class ProviderSettlementController extends Controller
{
    public function index(Request $request, ProviderSettlementService $service)
    {
        $service->authorize($request->user());
        $data = $request->validate(['provider_uuid' => 'nullable|uuid', 'status' => 'nullable|in:PENDING,IN_SERVICE,PAYABLE,REVIEW,SETTLED']);
        $query = ProviderPayable::with(['consumer', 'provider', 'snapshot', 'fulfillment.shipment']);
        if ($data['provider_uuid'] ?? null) {
            $query->where('provider_tenant_id', Tenant::where('uuid', $data['provider_uuid'])->firstOrFail()->id);
        }
        if ($data['status'] ?? null) {
            $query->whereRaw('(SELECT status FROM network_provider_payable_events WHERE provider_payable_id = network_provider_payables.id ORDER BY id DESC LIMIT 1) = ?', [$data['status']]);
        }

        return view('network.provider-settlements.index', ['payables' => $query->latest('id')->paginate(30)->withQueryString(), 'providers' => Tenant::whereIn('id', ProviderPayable::select('provider_tenant_id'))->orderBy('name')->get()]);
    }

    public function show(Request $request, string $payable, ProviderSettlementService $service)
    {
        $service->authorize($request->user());

        return view('network.provider-settlements.show', ['payable' => ProviderPayable::with(['consumer', 'provider', 'snapshot', 'fulfillment.shipment', 'settlements', 'events'])->where('uuid', $payable)->firstOrFail(),
            'idempotencyKey' => old('idempotency_key', (string) Str::uuid())]);
    }

    public function store(Request $request, string $payable, ProviderSettlementService $service)
    {
        $service->settle($request->user(), $payable, $request->all());

        return redirect()->route('network.provider-settlements.show', $payable)->with('success','Liquidación manual registrada; no se transfirió dinero desde esta aplicación.');
    }
}
