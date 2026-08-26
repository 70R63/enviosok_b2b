<?php
namespace App\Domain\AI\Commerce;
use App\Domain\AI\Usage\AiCapacityProvisioningService;use App\Domain\Network\Billing\Models\{Entitlement,Subscription};use App\Domain\Network\Catalog\Models\{Module,Plan};use App\Domain\Network\Tenancy\Models\Tenant;use Illuminate\Support\Facades\DB;use Illuminate\Support\Carbon;
final class AiTrialService {
 public function __construct(private AiCapacityProvisioningService $capacities) {}
 public function start(Tenant $tenant, Plan $plan): Subscription {return DB::transaction(function()use($tenant,$plan){$existing=Subscription::where('tenant_id',$tenant->id)->whereIn('status',['trial','trialing'])->where('trial_ends_at','>',now())->first();if($existing)return$existing;$start=now();$s=Subscription::create(['tenant_id'=>$tenant->id,'plan_id'=>$plan->id,'status'=>'trialing','started_at'=>$start,'current_period_start'=>$start,'current_period_end'=>$start->copy()->addDays(7),'trial_ends_at'=>$start->copy()->addDays(7),'billing_frequency'=>'TRIAL']);$module=Module::where('code','AI_CORE')->firstOrFail();$e=Entitlement::create(['subscription_id'=>$s->id,'tenant_id'=>$tenant->id,'module_id'=>$module->id,'code'=>'AI_CORE','is_enabled'=>true,'source'=>'override']);foreach([['MAX_AGENTS',1],['MAX_WEBCHAT_CHANNELS',1],['MAX_WHATSAPP_CHANNELS',0],['MONTHLY_CONVERSATIONS',50]] as [$c,$q])$this->capacities->override($e,$c,$q);return$s;});}
}
