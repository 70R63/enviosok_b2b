<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\ApiClient;
use App\Models\ApiUsageLog;
use App\Models\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CrmApiHubController extends Controller
{
    public function index(Request $request)
    {
        $query = ApiClient::query()
            ->with(['crmClient', 'keys'])
            ->orderByDesc('id');

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('plan')) {
            $query->where('plan', $request->plan);
        }

        if ($request->filled('active')) {
            $query->where('active', $request->active);
        }

        $apiClients = $query->paginate(15)->withQueryString();

        $apiClients->getCollection()->transform(function ($client) {
            $client->current_month_usage = ApiUsageLog::query()
                ->where('api_client_id', $client->id)
                ->where('endpoint', '!=', 'api/hub/ping')
                ->whereBetween('created_at', [
                    now()->startOfMonth(),
                    now()->endOfMonth(),
                ])
                ->count();

            $client->blocked_month_usage = ApiUsageLog::query()
                ->where('api_client_id', $client->id)
                ->where('status_code', 429)
                ->whereBetween('created_at', [
                    now()->startOfMonth(),
                    now()->endOfMonth(),
                ])
                ->count();

            $client->active_keys_count = $client->keys
                ->where('active', true)
                ->count();

            $client->last_used_at = $client->keys
                ->pluck('last_used_at')
                ->filter()
                ->max();

            return $client;
        });

        $totalApiClients = ApiClient::count();

        $activeApiClients = ApiClient::where('active', true)->count();

        $monthlyUsageTotal = ApiUsageLog::query()
            ->where('endpoint', '!=', 'api/hub/ping')
            ->whereBetween('created_at', [
                now()->startOfMonth(),
                now()->endOfMonth(),
            ])
            ->count();

        $monthlyBlockedTotal = ApiUsageLog::query()
            ->where('status_code', 429)
            ->whereBetween('created_at', [
                now()->startOfMonth(),
                now()->endOfMonth(),
            ])
            ->count();

        return view('crm.api_hub.index', compact(
            'apiClients',
            'totalApiClients',
            'activeApiClients',
            'monthlyUsageTotal',
            'monthlyBlockedTotal'
        ));
    }

    public function show(ApiClient $apiClient)
{
    $apiClient->load(['crmClient', 'keys']);

    $currentMonthUsage = ApiUsageLog::query()
        ->where('api_client_id', $apiClient->id)
        ->where('endpoint', '!=', 'api/hub/ping')
        ->whereBetween('created_at', [
            now()->startOfMonth(),
            now()->endOfMonth(),
        ])
        ->count();

    $blockedMonthUsage = ApiUsageLog::query()
        ->where('api_client_id', $apiClient->id)
        ->where('status_code', 429)
        ->whereBetween('created_at', [
            now()->startOfMonth(),
            now()->endOfMonth(),
        ])
        ->count();

    $latestLogs = ApiUsageLog::query()
        ->where('api_client_id', $apiClient->id)
        ->orderByDesc('id')
        ->limit(30)
        ->get();

    return view('crm.api_hub.show', compact(
        'apiClient',
        'currentMonthUsage',
        'blockedMonthUsage',
        'latestLogs'
    ));
}

    public function updatePlan(Request $request, ApiClient $apiClient)
    {
        $data = $request->validate([
            'plan' => ['required', 'in:FREE,STARTER,BUSINESS,ENTERPRISE'],
            'monthly_limit' => ['required', 'integer', 'min:1', 'max:10000000'],
        ]);

        $apiClient->update([
            'plan' => $data['plan'],
            'monthly_limit' => $data['monthly_limit'],
        ]);

        return redirect()
            ->route('crm.api-hub.show', $apiClient)
            ->with('success', 'Plan API actualizado correctamente.');
    }

    public function toggleActive(ApiClient $apiClient)
    {
        $apiClient->update([
            'active' => !$apiClient->active,
        ]);

        return redirect()
            ->route('crm.api-hub.show', $apiClient)
            ->with('success', 'Estado del cliente API actualizado correctamente.');
    }

    public function createApiKey(Request $request, ApiClient $apiClient)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'environment' => ['required', 'in:sandbox,production'],
        ]);

        /*
        * Regla comercial:
        * Solo una API Key activa por cliente y ambiente.
        * Si se genera una nueva sandbox, se desactivan las sandbox anteriores.
        * Si se genera una nueva production, se desactivan las production anteriores.
        */
        ApiKey::query()
            ->where('api_client_id', $apiClient->id)
            ->where('environment', $data['environment'])
            ->where('active', true)
            ->update([
                'active' => false,
            ]);

        $plainKey = 'zigo_sk_' . \Illuminate\Support\Str::random(48);

        ApiKey::create([
            'api_client_id' => $apiClient->id,
            'name' => $data['name'],
            'key_hash' => hash('sha256', $plainKey),
            'key_prefix' => substr($plainKey, 0, 20),
            'environment' => $data['environment'],
            'active' => true,
        ]);

        return redirect()
            ->route('crm.api-hub.show', $apiClient)
            ->with('success', 'API Key creada correctamente. Copia la llave ahora, no volverá a mostrarse.')
            ->with('new_api_key', $plainKey);
    }

    public function toggleApiKey(ApiClient $apiClient, ApiKey $apiKey)
    {
        if ($apiKey->api_client_id !== $apiClient->id) {
            abort(404);
        }

        $apiKey->update([
            'active' => !$apiKey->active,
        ]);

        return redirect()
            ->route('crm.api-hub.show', $apiClient)
            ->with('success', 'Estado de la API Key actualizado correctamente.');
    }
}