<?php
namespace App\Http\Controllers\Network;
use App\Domain\Network\Catalog\Models\Module;
use App\Domain\Network\Catalog\Models\Plan;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Domain\Network\Map\NetworkMapRegistry;
class NetworkDashboardController extends Controller
{
    public function __invoke(NetworkMapRegistry $registry)
    {
        return view('network.dashboard', [
            'activeTenants' => Tenant::where('status','active')->count(),
            'activePlans' => Plan::where('status','active')->count(),
            'activeModules' => Module::where('is_active',true)->count(),
            // Temporal hasta que el dominio Usage exista. No consulta tablas operativas.
            'monthlyOperations' => null,
            'tenants' => Tenant::latest()->limit(10)->get(),
            'statusCounts' => $registry->counts(),
            'statuses' => $registry->statuses(),
        ]);
    }
}
