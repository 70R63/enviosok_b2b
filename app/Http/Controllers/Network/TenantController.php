<?php
namespace App\Http\Controllers\Network;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Network\StoreTenantRequest;
use App\Http\Requests\Network\UpdateTenantRequest;
use App\Domain\Network\Catalog\Models\Plan;
use App\Domain\Network\Billing\{EntitlementService,SubscriptionService,UsageService};
use App\Domain\Network\Channels\B2C\Models\TenantOperation;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use App\Domain\Network\Commerce\Models\{PlatformPaymentAttempt,TenantOperationAllowance,TenantSaasOrder};
use App\Domain\Network\Onboarding\Models\SaasOnboardingApplication;
use App\Domain\Support\Models\SupportTicket;
use App\Domain\Network\Operations\NetworkAdminAuditService;
class TenantController extends Controller
{
    public function index(Request$request) { $q=Tenant::with(['currentPlan','primaryDomain','memberships.user','subscriptions'=>fn($q)=>$q->latest('started_at')])->when($request->filled('q'),fn($q)=>$q->where(function($x)use($request){$term='%'.$request->q.'%';$x->where('name','like',$term)->orWhere('slug','like',$term)->orWhereHas('domains',fn($d)=>$d->where('domain','like',$term))->orWhereHas('memberships.user',fn($u)=>$u->where('email','like',$term));}))->when($request->filled('status'),fn($q)=>$q->where('status',$request->status))->when($request->filled('plan_id'),fn($q)=>$q->where('current_plan_id',$request->plan_id));return view('network.tenants.index',['tenants'=>$q->latest()->paginate(20)->withQueryString(),'plans'=>Plan::orderBy('name')->get()]); }
    public function create() { return view('network.tenants.create'); }
    public function store(StoreTenantRequest $request,NetworkAdminAuditService$audit)
    {
        $tenant=Tenant::create($request->validated());$audit->record($request->user()->id,'tenant.created',$tenant,[],$tenant->toArray());
        return redirect()->route('network.tenants.show',$tenant)->with('success','Tenant creado correctamente.');
    }
    public function show(Tenant $tenant,SubscriptionService $subscriptions,EntitlementService $entitlements,UsageService $usage) { $tenant->load(['domains','branding','primaryDomain','memberships.user','subscriptions.plan','subscriptions.entitlements.module','currentPlan.modules'=>fn($q)=>$q->wherePivot('is_included',true)->orderBy('sort_order')])->loadCount(['memberships','memberships as active_memberships_count'=>fn($q)=>$q->where('status','active'),'memberships as owners_count'=>fn($q)=>$q->where('role','owner')->where('status','active')]);$subscription=$subscriptions->currentForTenant($tenant);$modules=$entitlements->enabledModules($tenant);$usageSummary=$subscription?$usage->summary($tenant,'operations',$subscription):null;$operations=Schema::hasTable('network_tenant_operations')?TenantOperation::where('tenant_id',$tenant->id)->latest()->limit(10)->get():collect();$orders=Schema::hasTable('tenant_saas_orders')?TenantSaasOrder::with('attempts')->where('tenant_id',$tenant->id)->latest()->get():collect();$attempts=Schema::hasTable('platform_payment_attempts')?PlatformPaymentAttempt::with('onboarding')->where(function($q)use($tenant){$q->where('tenant_id',$tenant->id)->orWhereHas('onboarding',fn($o)=>$o->where('tenant_id',$tenant->id));})->latest()->get():collect();$allowances=Schema::hasTable('tenant_operation_allowances')?TenantOperationAllowance::where('tenant_id',$tenant->id)->latest()->get():collect();$onboarding=Schema::hasTable('saas_onboarding_applications')?SaasOnboardingApplication::with('events')->where('tenant_id',$tenant->id)->latest()->first():null;$tickets=Schema::hasTable('support_tickets')?SupportTicket::where('tenant_id',$tenant->id)->latest()->get():collect(); return view('network.tenants.show',compact('tenant','subscription','modules','usageSummary','operations','orders','attempts','allowances','onboarding','tickets')); }
    public function edit(Tenant $tenant) { return view('network.tenants.edit',['tenant'=>$tenant,'plans'=>Plan::orderBy('name')->get()]); }
    public function update(UpdateTenantRequest $request,Tenant $tenant,NetworkAdminAuditService$audit)
    {
        $uuid=$tenant->uuid;$before=$tenant->toArray();$tenant->update($request->validated());$audit->record($request->user()->id,'tenant.updated',$tenant,$before,$tenant->fresh()->toArray());
        abort_unless($tenant->uuid===$uuid,500,'Tenant UUID is immutable.');
        return redirect()->route('network.tenants.show',$tenant)->with('success','Tenant actualizado correctamente.');
    }
}
