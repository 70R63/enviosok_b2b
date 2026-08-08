<?php
namespace App\Domain\Network\Billing;
use App\Domain\Network\Billing\Models\{Entitlement,Subscription};use App\Domain\Network\Catalog\Models\Plan;use App\Domain\Network\Tenancy\Models\Tenant;use Illuminate\Support\Collection;
final class EntitlementService
{
 public function snapshotFromPlan(Subscription$s,Plan$p):void{$modules=$p->modules()->wherePivot('is_included',true)->get();foreach($modules as$m)Entitlement::create(['subscription_id'=>$s->id,'tenant_id'=>$s->tenant_id,'module_id'=>$m->id,'code'=>$m->code,'is_enabled'=>true,'limit_value'=>$m->pivot->limit_value,'source'=>'plan']);}
 public function forSubscription(Subscription$s):Collection{return$s->entitlements()->with('module')->orderBy('code')->get();}
 public function forTenant(Tenant$t):Collection{$s=app(SubscriptionService::class)->currentForTenant($t);return$s?$this->forSubscription($s):collect();}
 public function has(Tenant$t,string$code):bool{$s=app(SubscriptionService::class)->currentForTenant($t);if($s)return$s->entitlements()->where('code',strtoupper($code))->where('is_enabled',true)->exists();return$t->currentPlan?->modules()->where('code',strtoupper($code))->wherePivot('is_included',true)->exists()??false;}
 public function limit(Tenant$t,string$code):?int{$s=app(SubscriptionService::class)->currentForTenant($t);if($s)return$s->entitlements()->where('code',strtoupper($code))->where('is_enabled',true)->value('limit_value');$module=$t->currentPlan?->modules()->where('code',strtoupper($code))->wherePivot('is_included',true)->first();return$module?->pivot?->limit_value;}
 public function enabledModules(Tenant$t):Collection{$s=app(SubscriptionService::class)->currentForTenant($t);if($s)return$s->entitlements()->with('module')->where('is_enabled',true)->get()->pluck('module')->filter()->values();return$t->currentPlan?->modules()->wherePivot('is_included',true)->orderBy('sort_order')->get()??collect();}
 public function usesFallback(Tenant$t):bool{return app(SubscriptionService::class)->currentForTenant($t)===null&&$t->current_plan_id!==null;}
}
