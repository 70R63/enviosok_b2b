<?php
namespace App\Http\Controllers\Network;
use App\Domain\Network\Catalog\Models\Module;
use App\Domain\Network\Catalog\Models\Plan;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Domain\Network\Map\NetworkMapRegistry;
use App\Domain\Network\Billing\Models\Subscription;
use Illuminate\Support\Facades\Schema;
use App\Domain\Network\Onboarding\Models\SaasOnboardingApplication;
use App\Domain\Network\Commerce\Models\PlatformPaymentAttempt;
use App\Domain\Network\Commerce\Models\TenantSaasOrder;
use App\Domain\Support\Models\SupportTicket;
class NetworkDashboardController extends Controller
{
    public function __invoke(NetworkMapRegistry $registry)
    {
        $subscriptionCounts=array_fill_keys(Subscription::STATUSES,0);if(Schema::hasTable('network_subscriptions'))$subscriptionCounts=array_merge($subscriptionCounts,Subscription::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total','status')->all());return view('network.dashboard', [
            'activeTenants' => Tenant::where('status','active')->count(),
            'activePlans' => Plan::where('status','active')->count(),
            'activeModules' => Module::where('is_active',true)->count(),
            // Temporal hasta que el dominio Usage exista. No consulta tablas operativas.
            'monthlyOperations' => null,
            'tenants' => Tenant::with(['currentPlan.modules'=>fn($q)=>$q->wherePivot('is_included',true)])->latest()->limit(10)->get(),
            'statusCounts' => $registry->counts(),
            'statuses' => $registry->statuses(),
            'subscriptionCounts'=>$subscriptionCounts,
            'pendingOnboardings'=>Schema::hasTable('saas_onboarding_applications')?SaasOnboardingApplication::whereIn('status',['PENDING_PAYMENT','PAID','PROVISIONING'])->count():0,
            'failedOnboardings'=>Schema::hasTable('saas_onboarding_applications')?SaasOnboardingApplication::where('status','FAILED')->count():0,
            'recentApprovedPayments'=>Schema::hasTable('platform_payment_attempts')?PlatformPaymentAttempt::where('status','APPROVED')->where('approved_at','>=',now()->subDays(7))->count():0,
            'approvedFailed'=>Schema::hasTable('platform_payment_attempts')?PlatformPaymentAttempt::where('status','APPROVED')->whereHas('onboarding',fn($q)=>$q->where('status','FAILED'))->count():0,
            'openTickets'=>Schema::hasTable('support_tickets')?SupportTicket::whereNotIn('status',['RESOLVED','CLOSED'])->count():0,
            'expiringSubscriptions'=>Schema::hasTable('network_subscriptions')?Subscription::whereIn('status',['active','trial','past_due','grace'])->whereBetween('current_period_end',[now(),now()->addDays(max(config('zigo_billing.reminder_days',[30])))])->count():0,
            'expiredSubscriptions'=>Schema::hasTable('network_subscriptions')?Subscription::whereIn('status',['active','trial','past_due','grace'])->where('current_period_end','<',now())->count():0,
            'pendingRenewals'=>Schema::hasTable('tenant_saas_orders')?TenantSaasOrder::where('purchase_key','like','renewal:%')->whereIn('status',['DRAFT','PENDING_PAYMENT'])->count():0,
            'paidRenewals'=>Schema::hasTable('tenant_saas_orders')?TenantSaasOrder::where('purchase_key','like','renewal:%')->whereIn('status',['PAID','ACTIVATED'])->count():0,
            'confirmedSaasRevenue'=>Schema::hasTable('platform_payment_attempts')?PlatformPaymentAttempt::where('status','APPROVED')->sum('amount'):0,
        ]);
    }
}
