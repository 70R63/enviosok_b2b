<?php
namespace App\Domain\AI\Usage;
use App\Domain\Network\Billing\Models\Entitlement;use App\Domain\Network\Tenancy\Models\Tenant;use Illuminate\Support\Facades\DB;
final class AiCapacityProvisioningService{
 public function __construct(private AiCapacityService$capacity){}
 public function addon(Entitlement$e,string$capability,string$key,int$quantity,bool$enabled=true):void{$this->write($e,$capability,'addon',$key,$quantity,$enabled);}
 public function override(Entitlement$e,string$capability,int$quantity,bool$enabled=true):void{$this->write($e,$capability,'override','override',$quantity,$enabled);}
 private function write(Entitlement$e,string$c,string$source,string$key,int$q,bool$enabled):void{if($q<0||!in_array($c,$this->capabilities(),true)||!in_array($source,['addon','override'],true))throw new \InvalidArgumentException('Invalid AI capacity grant.');DB::transaction(function()use($e,$c,$source,$key,$q,$enabled){$tenant=Tenant::query()->findOrFail($e->tenant_id);$this->capacity->lockAuthority($tenant);$locked=Entitlement::query()->where('tenant_id',$e->tenant_id)->where('subscription_id',$e->subscription_id)->findOrFail($e->id);if($locked->code!=='AI_CORE')throw new \DomainException('AI capacity requires AI_CORE.');DB::table('network_entitlement_capacities')->updateOrInsert(['entitlement_id'=>$locked->id,'capability_code'=>$c,'source'=>$source,'source_key'=>$source==='override'?'override':$key],['tenant_id'=>$locked->tenant_id,'subscription_id'=>$locked->subscription_id,'quantity'=>$q,'is_enabled'=>$enabled,'created_at'=>now(),'updated_at'=>now()]);});}
 private function capabilities():array{return[AiCapacityService::MAX_AGENTS,AiCapacityService::MAX_WEBCHAT_CHANNELS,AiCapacityService::MAX_WHATSAPP_CHANNELS,AiCapacityService::MONTHLY_RUNTIME_UNITS,AiCapacityService::MONTHLY_ACTION_RUNS,AiCapacityService::MONTHLY_CONVERSATIONS];}
}
