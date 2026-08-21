<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Network\Tenancy\TenantAccessService;
use App\Domain\Network\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Domain\Network\Billing\{EntitlementService,SubscriptionService,UsageService};
use App\Domain\Network\ProductShell\{TenantWorkspace, TenantWorkspaceResolver};
use App\Domain\AI\Agents\Models\Agent;

final class TenantAdminController extends Controller
{
    public function dashboard(TenantContext $context, TenantAccessService $access, SubscriptionService $subscriptions, EntitlementService $entitlements, UsageService $usage, TenantWorkspaceResolver $workspaces)
    {
        $tenant = $this->tenant($context);$subscription=$subscriptions->currentForTenant($tenant);$modules=$entitlements->enabledModules($tenant);$usageSummary=$subscription?$usage->summary($tenant,'operations',$subscription):null;
        $workspace=$workspaces->resolveForPresentation($tenant);$agentSummary=$workspace===TenantWorkspace::ZigoAi?['total'=>Agent::count(),'drafts'=>Agent::where('status','draft')->count()]:null;
        return view('tenant.admin.dashboard', ['tenant'=>$tenant,'membership'=>$access->membership(auth()->user()),'subscription'=>$subscription,'plan'=>$subscription?->plan??$tenant->currentPlan,'modules'=>$modules,'usageSummary'=>$usageSummary,'usesFallback'=>$entitlements->usesFallback($tenant),'workspace'=>$workspace,'agentSummary'=>$agentSummary]);
    }

    public function plan(TenantContext $context, SubscriptionService $subscriptions, EntitlementService $entitlements, UsageService $usage)
    {
        $tenant=$this->tenant($context);$subscription=$subscriptions->currentForTenant($tenant);return view('tenant.admin.plan',['tenant'=>$tenant,'subscription'=>$subscription,'plan'=>$subscription?->plan??$tenant->currentPlan,'modules'=>$entitlements->enabledModules($tenant),'usageSummary'=>$subscription?$usage->summary($tenant,'operations',$subscription):null,'usesFallback'=>$entitlements->usesFallback($tenant)]);
    }

    private function tenant(TenantContext $context)
    {
        return $context->tenant()->load(['branding', 'currentPlan.modules' => fn ($query) => $query->wherePivot('is_included', true)->orderBy('sort_order')]);
    }
}
