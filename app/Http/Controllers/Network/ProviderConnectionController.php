<?php

namespace App\Http\Controllers\Network;

use App\Domain\Network\Providers\LocalProviderCommercialPricing;
use App\Domain\Network\Providers\Models\ProviderConnection;
use App\Domain\Network\Providers\ProviderConnectionService;
use App\Domain\Network\Providers\ProviderNetworkReadinessService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ProviderConnectionController extends Controller
{
    public function index(Request $request, ProviderConnectionService $service)
    {
        $service->authorizeAdministrator($request->user());

        return view('network.provider-connections.index', ['connections' => ProviderConnection::with(['consumer', 'provider'])->latest()->paginate(30)]);
    }

    public function create(Request $request, ProviderConnectionService $service)
    {
        return $this->form($request, $service, null);
    }

    public function edit(Request $request, ProviderConnection $connection, ProviderConnectionService $service)
    {
        return $this->form($request, $service, $connection);
    }

    private function form(Request $request, ProviderConnectionService $service, ?ProviderConnection $connection)
    {
        $tenants = $service->administratorTenants($request->user());
        $request->validate(['provider_uuid' => 'nullable|uuid', 'consumer_uuid' => 'nullable|uuid']);
        $providerUuid = $connection?->provider?->uuid ?? $request->query('provider_uuid');
        $consumerUuid = $connection?->consumer?->uuid ?? $request->query('consumer_uuid');
        $provider = $providerUuid ? $service->administratorTenant($request->user(), $providerUuid) : null;
        $consumer = $consumerUuid ? $service->administratorTenant($request->user(), $consumerUuid) : null;

        return view('network.provider-connections.form', [
            'connection' => $connection, 'tenants' => $tenants, 'provider' => $provider, 'consumer' => $consumer,
            'services' => $provider ? $service->administratorServices($request->user(), $provider) : collect(),
            'authorized' => $connection ? DB::table('network_provider_connection_services')->where('connection_id', $connection->id)->pluck('local_shipping_service_id')->all() : [],
            'report' => $connection ? app(ProviderNetworkReadinessService::class)->evaluate($consumer, $provider, $connection, $connection->status === 'inactive') : null,
            'commercialKey' => $consumer && $provider ? app(LocalProviderCommercialPricing::class)->carrierKey($consumer, $provider) : null,
        ]);
    }

    public function store(Request $request, ProviderConnectionService $service)
    {
        $connection = $service->saveConfiguration($request->user(), null, $request->all());

        return redirect()->route('network.provider-connections.edit', $connection)->with('success', 'Relación guardada.');
    }

    public function update(Request $request, ProviderConnection $connection, ProviderConnectionService $service)
    {
        $connection = $service->saveConfiguration($request->user(), $connection, $request->all());

        return redirect()->route('network.provider-connections.edit', $connection)->with('success', 'Relación guardada.');
    }
}
