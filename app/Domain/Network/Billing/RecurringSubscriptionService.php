<?php
namespace App\Domain\Network\Billing;
use App\Domain\Network\Billing\Contracts\RecurringSubscriptionProvider;
use App\Domain\Network\Billing\Models\Subscription;
use App\Domain\Network\Commerce\Models\{NetworkCommercialProduct,PlatformPaymentEvent};
use App\Domain\Network\Billing\Models\Entitlement;
use App\Domain\Network\Catalog\Models\Module;
use App\Domain\AI\Usage\AiCapacityProvisioningService;
use App\Domain\Network\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
final class RecurringSubscriptionService
{
 public function __construct(private RecurringSubscriptionProvider $provider,private SubscriptionService $subscriptions,private AiCapacityProvisioningService $capacities){}
 public function syncPlan(NetworkCommercialProduct $product,string $frequency):NetworkCommercialProduct
 {
  $meta=$product->metadata??[]; $key=strtolower($frequency)==='annual'?'provider_plan_id_annual':'provider_plan_id_monthly'; if(!empty($meta[$key]))return$product;
  $amount=(string)$product->price; $payload=['reason'=>$product->name,'auto_recurring'=>['frequency'=>strtolower($frequency)==='annual'?12:1,'frequency_type'=>'months','transaction_amount'=>(float)$amount,'currency_id'=>$product->currency],'back_url'=>url('/agentes-ia')]; $response=$this->provider->createPlan($payload); $meta[$key]=$response['id']??throw new RuntimeException('RECURRING_PLAN_ID_MISSING'); $product->update(['metadata'=>$meta]); return$product->fresh();
 }
 public function create(Tenant $tenant,Subscription $subscription,NetworkCommercialProduct $product,string $frequency,string $email):array
 {
  $product=$this->syncPlan($product,$frequency); $meta=$product->metadata??[]; $planId=$meta[strtolower($frequency)==='annual'?'provider_plan_id_annual':'provider_plan_id_monthly']??null; if(!$planId)throw new RuntimeException('RECURRING_PLAN_REQUIRED'); $reference='ai-sub:'.$subscription->uuid;
  return DB::transaction(function()use($tenant,$subscription,$product,$frequency,$email,$planId,$reference){$locked=Subscription::whereKey($subscription->id)->lockForUpdate()->firstOrFail(); if($locked->provider_subscription_id)return['id'=>$locked->provider_subscription_id,'status'=>$locked->provider_status]; $response=$this->provider->createSubscription(['preapproval_plan_id'=>$planId,'external_reference'=>$reference,'payer_email'=>$email,'back_url'=>url('/admin/plan')]); $locked->update(['provider_subscription_id'=>$response['id']??null,'provider_plan_id'=>$planId,'provider_status'=>$response['status']??'pending','billing_frequency'=>strtoupper($frequency)]); return$response;});
 }
 public function reconcile(Subscription $subscription):Subscription
 {
  if(!$subscription->provider_subscription_id)return$subscription; $remote=$this->provider->retrieve($subscription->provider_subscription_id); if(isset($remote['external_reference'])&&$remote['external_reference']!=='ai-sub:'.$subscription->uuid)throw new RuntimeException('RECURRING_REFERENCE_MISMATCH'); $status=strtolower((string)($remote['status']??'')); $mapped=match($status){'authorized','approved'=>'active','paused'=>'past_due','cancelled','canceled'=>'canceled',default=>$subscription->status}; $updates=['provider_status'=>$status,'status'=>$mapped]; if(!empty($remote['next_payment_date']))$updates['next_payment_date']=$remote['next_payment_date']; if($mapped==='active'){ $product=NetworkCommercialProduct::query()->whereJsonContains('metadata->provider_plan_id_monthly',(string)($remote['preapproval_plan_id']??''))->orWhereJsonContains('metadata->provider_plan_id_annual',(string)($remote['preapproval_plan_id']??''))->first(); if(!$product)throw new RuntimeException('RECURRING_PLAN_MISMATCH'); $amount=data_get($remote,'auto_recurring.transaction_amount'); $currency=data_get($remote,'auto_recurring.currency_id'); if($amount!==null&&bccomp((string)$amount,(string)$product->price,2)!==0)throw new RuntimeException('RECURRING_AMOUNT_MISMATCH'); if($currency!==null&&(string)$currency!==$product->currency)throw new RuntimeException('RECURRING_CURRENCY_MISMATCH'); if($product->plan_id)$updates['plan_id']=$product->plan_id; if(!$subscription->current_period_end||!$subscription->current_period_end->isFuture())$updates['current_period_end']=now()->addMonthsNoOverflow($subscription->billing_frequency==='ANNUAL'?12:1); } $subscription->update($updates); if($mapped==='active')$this->applyPlanCapacity($subscription->fresh()); return$subscription->fresh();
  }
 private function applyPlanCapacity(Subscription $subscription): void
 { $module=Module::where('code','AI_CORE')->first(); $ent=$module?->id?Entitlement::where('subscription_id',$subscription->id)->where('module_id',$module->id)->where('code','AI_CORE')->first():null; if(!$ent)return; foreach(DB::table('network_plan_module_capacities')->where('plan_id',$subscription->plan_id)->where('module_id',$module->id)->get() as $row)$this->capacities->override($ent,$row->capability_code,(int)$row->quantity); }
 public function cancelAtPeriodEnd(Subscription $subscription):Subscription{return DB::transaction(function()use($subscription){$s=Subscription::whereKey($subscription->id)->lockForUpdate()->firstOrFail();$s->update(['cancel_at_period_end'=>true]);return$s->fresh();});}
 public function renew(Subscription $subscription,string $paymentId):Subscription
 {
  return DB::transaction(function()use($subscription,$paymentId){$event=PlatformPaymentEvent::firstOrCreate(['provider'=>'MERCADO_PAGO','event_key'=>'renewal:'.$paymentId],['provider_payment_id'=>$paymentId,'status'=>'PROCESSING','received_at'=>now()]); if($event->status==='PROCESSED')return$subscription->fresh(); $s=Subscription::whereKey($subscription->id)->lockForUpdate()->firstOrFail(); $months=$s->billing_frequency==='ANNUAL'?12:1; $s->update(['status'=>'active','current_period_start'=>$s->current_period_end,'current_period_end'=>$s->current_period_end->copy()->addMonthsNoOverflow($months),'next_payment_date'=>$s->current_period_end->copy()->addMonthsNoOverflow($months)]); $s->events()->create(['tenant_id'=>$s->tenant_id,'event'=>'renewed','from_status'=>$subscription->status,'to_status'=>'active','metadata'=>['payment_id'=>$paymentId]]); $event->update(['status'=>'PROCESSED','processed_at'=>now()]); return$s->fresh();});
 }
 public function scheduleDowngrade(Subscription $subscription,int $planId):Subscription
 { $subscription->update(['pending_plan_id'=>$planId,'pending_effective_at'=>$subscription->current_period_end]); return$subscription->fresh(); }
 public function webhook(string $providerId,string $eventKey):?Subscription
 {
  $subscription=Subscription::where('provider_subscription_id',$providerId)->first(); if(!$subscription)return null; $event=PlatformPaymentEvent::firstOrCreate(['provider'=>'MERCADO_PAGO','event_key'=>$eventKey],['provider_payment_id'=>$providerId,'status'=>'RECEIVED','received_at'=>now()]); if($event->status==='PROCESSED')return$subscription; $this->reconcile($subscription); $event->update(['status'=>'PROCESSED','processed_at'=>now()]); return$subscription->fresh();
 }
 public function paymentWebhook(string $paymentId,string $eventKey):?Subscription
 { $payment=$this->provider->retrievePayment($paymentId); $providerSubscription=(string)($payment['preapproval_id']??$payment['subscription_id']??''); $subscription=$providerSubscription?Subscription::where('provider_subscription_id',$providerSubscription)->first():null; if(!$subscription)return null; $event=PlatformPaymentEvent::firstOrCreate(['provider'=>'MERCADO_PAGO','event_key'=>$eventKey],['provider_payment_id'=>$paymentId,'status'=>'RECEIVED','received_at'=>now()]); if($event->status==='PROCESSED')return$subscription; if(strtolower((string)($payment['status']??''))!=='approved')return$subscription; $result=$this->renew($subscription,$paymentId); $event->update(['status'=>'PROCESSED','processed_at'=>now()]); return$result; }
}
