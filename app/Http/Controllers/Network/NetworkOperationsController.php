<?php
namespace App\Http\Controllers\Network;

use App\Domain\Network\Billing\Models\Subscription;
use App\Domain\Network\Billing\{SubscriptionService,UsageService};
use App\Domain\Network\Commerce\Models\{PlatformPaymentAttempt,TenantSaasOrder};
use App\Domain\Network\Onboarding\Models\SaasOnboardingApplication;
use App\Domain\Network\Onboarding\Services\OwnerActivationService;
use App\Domain\Network\Operations\Models\NetworkAdminAuditEvent;
use App\Domain\Network\Operations\NetworkAdminAuditService;
use App\Domain\Network\Tenancy\Models\{Tenant,TenantMembership};
use App\Domain\Network\Tenancy\{TenantAccessService,TenantContext};
use App\Http\Controllers\Controller;
use App\Models\{User,ZigoNotificationDelivery};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Password,Schema};
use Illuminate\Validation\Rule;

final class NetworkOperationsController extends Controller
{
 public function users(Request$r){$q=TenantMembership::with(['tenant','user'])->when($r->filled('q'),fn($q)=>$q->whereHas('user',fn($u)=>$u->where('email','like','%'.$r->q.'%')->orWhere('name','like','%'.$r->q.'%')))->when($r->filled('tenant_id'),fn($q)=>$q->where('tenant_id',$r->tenant_id))->when($r->filled('status'),fn($q)=>$q->where('status',$r->status));return view('network.operations.users',['memberships'=>$q->latest()->paginate(30)->withQueryString(),'tenants'=>Tenant::orderBy('name')->get()]);}
 public function tenantUsers(Tenant$tenant){return redirect()->route('network.users.index',['tenant_id'=>$tenant->id]);}
 public function membership(Request$r,Tenant$tenant,TenantMembership$membership,TenantContext$context,TenantAccessService$access,NetworkAdminAuditService$audit){abort_unless($membership->tenant_id===$tenant->id,404);$data=$r->validate(['role'=>['required',Rule::in(TenantMembership::ROLES)],'status'=>['required',Rule::in(TenantMembership::STATUSES)]]);$before=$membership->only(['role','status']);$context->set($tenant);$access->updateMembership($membership,$data,$r->user(),true);$audit->record($r->user()->id,'membership.updated',$membership,$before,$membership->fresh()->only(['role','status']));return back()->with('success','Acceso tenant actualizado.');}
 public function reset(Request$r,Tenant$tenant,TenantMembership$membership,NetworkAdminAuditService$audit){abort_unless($membership->tenant_id===$tenant->id,404);Password::broker()->sendResetLink(['email'=>$membership->user->email]);$audit->record($r->user()->id,'membership.password_reset_requested',$membership);return back()->with('success','Flujo estándar de recuperación solicitado.');}
 public function activation(Request$r,Tenant$tenant,TenantMembership$membership,OwnerActivationService$activation,NetworkAdminAuditService$audit){abort_unless($membership->tenant_id===$tenant->id,404);$app=SaasOnboardingApplication::where('tenant_id',$tenant->id)->where('owner_user_id',$membership->user_id)->where('status','ACTIVE')->firstOrFail();if(Schema::hasTable('zigo_notification_deliveries'))ZigoNotificationDelivery::where('event_key','saas-owner-welcome:'.$app->uuid)->whereIn('status',['SENT','FAILED'])->delete();$activation->sendIfNeeded($app);$audit->record($r->user()->id,'membership.activation_resent',$membership);return back()->with('success','Activación reenviada de forma segura.');}
 public function subscriptions(Request$r,UsageService$usage){$q=Subscription::with(['tenant','plan'])->when($r->filled('tenant_id'),fn($q)=>$q->where('tenant_id',$r->tenant_id))->when($r->filled('plan_id'),fn($q)=>$q->where('plan_id',$r->plan_id))->when($r->filled('status'),fn($q)=>$q->where('status',$r->status));return view('network.operations.subscriptions',['subscriptions'=>$q->latest()->paginate(30)->withQueryString()]);}
 public function subscription(Subscription$subscription,UsageService$usage){$subscription->load(['tenant','plan','entitlements.module','events']);return view('network.operations.subscription',compact('subscription')+['usage'=>$usage->summary($subscription->tenant,'operations',$subscription)]);}
 public function subscriptionStatus(Request$r,Subscription$subscription,SubscriptionService$service,NetworkAdminAuditService$audit){$data=$r->validate(['status'=>['required',Rule::in(['active','suspended'])]]);$before=$subscription->status;$service->changeStatus($subscription,$data['status'],$r->user()->id);$audit->record($r->user()->id,'subscription.status_changed',$subscription,['status'=>$before],['status'=>$data['status']]);return back()->with('success','Suscripción actualizada.');}
 public function payments(Request$r){$q=PlatformPaymentAttempt::with(['order.tenant','onboarding.tenant'])->when($r->filled('status'),fn($q)=>$q->where('status',$r->status))->when($r->filled('provider'),fn($q)=>$q->where('provider',$r->provider))->when($r->filled('tenant_id'),fn($q)=>$q->where(function($x)use($r){$x->where('tenant_id',$r->tenant_id)->orWhereHas('onboarding',fn($o)=>$o->where('tenant_id',$r->tenant_id));}));return view('network.operations.payments',['attempts'=>$q->latest()->paginate(40)->withQueryString()]);}
 public function payment(PlatformPaymentAttempt$attempt){$attempt->load(['order.tenant','order.product','onboarding.tenant']);$events=$attempt->hasMany(\App\Domain\Network\Commerce\Models\PlatformPaymentEvent::class,'platform_payment_attempt_id')->latest('received_at')->get();return view('network.operations.payment',compact('attempt','events'));}
 public function audit(){return view('network.operations.audit',['events'=>NetworkAdminAuditEvent::with('actor')->latest('created_at')->paginate(50)]);}
}
