<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\AI\Leads\Models\Lead;
use App\Domain\AI\Tenancy\AiTenantBoundary;
use App\Http\Controllers\Controller;
use App\Http\Support\AiLaunchpadHttpGate;

final class AiLeadController extends Controller
{
    public function index(AiLaunchpadHttpGate $gate, AiTenantBoundary $tenants)
    {
        $gate->ensure(auth()->user());

        return view('tenant.admin.ai-leads.index', ['tenant' => $tenants->requireTenant(), 'leads' => Lead::query()->with(['agent', 'conversation', 'outcomes'])->latest('detected_at')->paginate(20)]);
    }

    public function show(Lead $lead, AiLaunchpadHttpGate $gate, AiTenantBoundary $tenants)
    {
        $gate->ensure(auth()->user());
        $gate->assertCurrentTenant($lead);
        $lead->load(['agent', 'agentVersion', 'conversation', 'outcomes']);

        return view('tenant.admin.ai-leads.show', ['tenant' => $tenants->requireTenant(), 'lead' => $lead]);
    }
}
