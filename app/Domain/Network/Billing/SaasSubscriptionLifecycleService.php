<?php

namespace App\Domain\Network\Billing;

use App\Domain\Network\Billing\Models\Subscription;
use App\Domain\Network\Commerce\Models\NetworkCommercialProduct;
use App\Domain\Network\Commerce\Models\TenantSaasOrder;
use App\Domain\Network\Commerce\TenantSaasOrderService;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class SaasSubscriptionLifecycleService
{
    public function __construct(private SubscriptionService $subscriptions, private TenantSaasOrderService $orders, private SaasBillingNotificationService $notifications) {}

    public function expiring(?int $tenantId=null)
    {
        $max=max(config('zigo_billing.reminder_days',[30]));
        return Subscription::with(['tenant','plan'])->whereIn('status',['trial','active','past_due','grace'])->whereBetween('current_period_end',[now()->startOfDay(),now()->addDays($max)->endOfDay()])->when($tenantId,fn($q)=>$q->where('tenant_id',$tenantId))->get();
    }

    public function expired(?int $tenantId=null)
    {
        return Subscription::with(['tenant','plan'])->whereIn('status',['trial','active','past_due','grace'])->where('current_period_end','<',now())->when($tenantId,fn($q)=>$q->where('tenant_id',$tenantId))->get();
    }

    public function notifyDue(Subscription $subscription): bool
    {
        $days=now()->startOfDay()->diffInDays($subscription->current_period_end->copy()->startOfDay(),false);
        if($days<0){$renewal=$this->renewalForNotice($subscription);$sent=$this->notifications->send($subscription,'SUBSCRIPTION_EXPIRED',$renewal);if(abs($days)<(int)config('zigo_billing.suspend_after_days',0))$sent=$this->notifications->send($subscription,'SUSPENSION_WARNING',$renewal)||$sent;return$sent;}
        if(!in_array($days,config('zigo_billing.reminder_days',[]),true))return false;
        $renewal=$this->renewalForNotice($subscription);
        return$this->notifications->send($subscription,$days===0?'SUBSCRIPTION_EXPIRES_TODAY':'SUBSCRIPTION_EXPIRING',$renewal);
    }

    public function sendReminder(Subscription $subscription): bool
    {
        $days=now()->startOfDay()->diffInDays($subscription->current_period_end->copy()->startOfDay(),false);
        return$this->notifications->send($subscription,$days<0?'SUBSCRIPTION_EXPIRED':($days===0?'SUBSCRIPTION_EXPIRES_TODAY':'SUBSCRIPTION_EXPIRING'),$this->renewalForNotice($subscription));
    }

    public function generateRenewal(Subscription $subscription, ?User $actor=null): TenantSaasOrder
    {
        $key=$this->renewalKey($subscription);
        $existing=TenantSaasOrder::where('tenant_id',$subscription->tenant_id)->where('purchase_key',$key)->first();
        if($existing)return$existing;
        $actor??=$subscription->tenant->memberships()->where('role','owner')->where('status','active')->with('user')->first()?->user;
        if(!$actor)throw ValidationException::withMessages(['renewal'=>'El tenant no tiene owner activo para gestionar la renovación.']);
        $offer=NetworkCommercialProduct::where('type','PLAN')->where('plan_id',$subscription->plan_id)->where('billing_type',$this->billingType($subscription))->where('is_active',true)->whereNull('archived_at')->orderBy('display_order')->first();
        if(!$offer)throw ValidationException::withMessages(['renewal'=>'No existe una oferta comercial activa para este plan y periodicidad.']);
        return$this->orders->create($subscription->tenant,$actor,$offer,$key);
    }

    public function suspendExpired(Subscription $subscription,?int $actorId=null):Subscription
    {
        if($subscription->current_period_end->copy()->addDays((int)config('zigo_billing.suspend_after_days',0))->isFuture())return$subscription;
        $renewal=$this->renewalForNotice($subscription);
        if($subscription->status!=='suspended')$subscription=$this->subscriptions->changeStatus($subscription,'suspended',$actorId);
        $subscription->tenant()->update(['status'=>'suspended']);
        $this->notifications->send($subscription,'SUBSCRIPTION_SUSPENDED',$renewal);
        return$subscription->fresh();
    }

    public function reactivateAfterVerifiedRenewal(TenantSaasOrder $order):void
    {
        $subscription=$this->subscriptions->currentForTenant($order->tenant);if(!$subscription)return;
        $order->tenant()->update(['status'=>'active','archived_at'=>null,'archive_reason'=>null]);
        $this->notifications->send($subscription,'RENEWAL_PAYMENT_CONFIRMED',$order);
        $this->notifications->send($subscription,'SUBSCRIPTION_REACTIVATED',$order);
    }

    private function renewalForNotice(Subscription $subscription):?TenantSaasOrder
    {
        try{return$this->generateRenewal($subscription);}catch(ValidationException){return TenantSaasOrder::where('tenant_id',$subscription->tenant_id)->where('purchase_key',$this->renewalKey($subscription))->first();}
    }
    private function renewalKey(Subscription $subscription):string{return'renewal:'.$subscription->uuid.':'.$subscription->current_period_end->toDateString();}
    private function billingType(Subscription $subscription):string{return$subscription->current_period_start&&$subscription->current_period_end&&$subscription->current_period_start->diffInMonths($subscription->current_period_end)>=10?'ANNUAL':'MONTHLY';}
}
