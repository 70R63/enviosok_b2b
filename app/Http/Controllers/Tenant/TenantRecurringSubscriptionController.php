<?php
namespace App\Http\Controllers\Tenant;
use App\Domain\Network\Billing\{RecurringSubscriptionService,SubscriptionService};
use App\Domain\Network\Commerce\Models\NetworkCommercialProduct;
use App\Domain\Network\Tenancy\TenantContext;
use Illuminate\Http\Request;
final class TenantRecurringSubscriptionController
{
 public function subscribe(Request $request,TenantContext $context,SubscriptionService $subscriptions,RecurringSubscriptionService $recurring)
 {
  $data=$request->validate(['product_uuid'=>['required','uuid'],'frequency'=>['required','in:MONTHLY,ANNUAL']]); $product=NetworkCommercialProduct::where('uuid',$data['product_uuid'])->where('is_active',true)->whereJsonContains('metadata->ai_product',true)->firstOrFail(); $subscription=$subscriptions->currentForTenant($context->tenant()); abort_unless($subscription,409); $recurring->create($context->tenant(),$subscription,$product,$data['frequency'],(string)$request->user()->email); return back()->with('status','Estamos confirmando tu suscripción.');
 }
 public function reconcile(TenantContext $context,SubscriptionService $subscriptions,RecurringSubscriptionService $recurring)
 { $subscription=$subscriptions->currentForTenant($context->tenant()); abort_unless($subscription,404); $recurring->reconcile($subscription); return back()->with('status','Estado de suscripción actualizado.'); }
 public function cancel(TenantContext $context,SubscriptionService $subscriptions,RecurringSubscriptionService $recurring)
 { $subscription=$subscriptions->currentForTenant($context->tenant()); abort_unless($subscription,404); $recurring->cancelAtPeriodEnd($subscription); return back()->with('status','Tu plan permanecerá activo hasta el final del periodo.'); }
 public function change(Request $request,TenantContext $context,SubscriptionService $subscriptions,RecurringSubscriptionService $recurring)
 { $data=$request->validate(['product_uuid'=>['required','uuid'],'frequency'=>['required','in:MONTHLY,ANNUAL']]);$product=NetworkCommercialProduct::where('uuid',$data['product_uuid'])->where('is_active',true)->whereJsonContains('metadata->ai_product',true)->firstOrFail();$subscription=$subscriptions->currentForTenant($context->tenant());abort_unless($subscription,404);$current=NetworkCommercialProduct::where('plan_id',$subscription->plan_id)->whereJsonContains('metadata->ai_product',true)->first();$from=(int)data_get($current?->metadata,'ai_tier_rank',0);$to=(int)data_get($product->metadata,'ai_tier_rank',0);if($to>$from){$recurring->requestUpgrade($subscription,$product,$data['frequency'],(string)$request->user()->email);return back()->with('status','Estamos confirmando el cambio de tu plan.');}$recurring->scheduleDowngrade($subscription,(int)$product->plan_id);return back()->with('status','Tu nuevo plan se aplicará en tu próxima renovación.'); }
}
