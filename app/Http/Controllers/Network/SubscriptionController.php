<?php
namespace App\Http\Controllers\Network;
use App\Domain\Network\Billing\Models\Subscription;use App\Domain\Network\Billing\{SubscriptionService,UsageService};use App\Domain\Network\Catalog\Models\Plan;use App\Domain\Network\Tenancy\Models\Tenant;use App\Http\Controllers\Controller;use App\Http\Requests\Network\{RecordUsageRequest,StoreSubscriptionRequest,UpdateSubscriptionStatusRequest};
final class SubscriptionController extends Controller
{
 public function index(Tenant$tenant,SubscriptionService$s,UsageService$usage){$current=$s->currentForTenant($tenant);$history=$s->historyForTenant($tenant);$summary=$current?$usage->summary($tenant,'operations',$current):null;return view('network.tenants.subscriptions',compact('tenant','current','history','summary')+['plans'=>Plan::where('status','active')->orderBy('name')->get()]);}
 public function store(StoreSubscriptionRequest$request,Tenant$tenant,SubscriptionService$s){$s->create($tenant,$request->validated(),$request->user()->id);return back()->with('success','Suscripción creada y entitlements congelados.');}
 public function status(UpdateSubscriptionStatusRequest$request,Tenant$tenant,Subscription$subscription,SubscriptionService$s){abort_unless($subscription->tenant_id===$tenant->id,404);$s->changeStatus($subscription,$request->validated('status'),$request->user()->id);return back()->with('success','Estado de suscripción actualizado.');}
 public function usage(RecordUsageRequest$request,Tenant$tenant,UsageService$usage){$usage->record($tenant,$request->validated('metric'),$request->validated('quantity'),$request->validated('idempotency_key'));return back()->with('success','Evento de uso registrado.');}
}
