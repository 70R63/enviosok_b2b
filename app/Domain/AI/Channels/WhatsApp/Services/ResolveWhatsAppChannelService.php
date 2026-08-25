<?php
namespace App\Domain\AI\Channels\WhatsApp\Services;
use App\Domain\AI\Agents\Enums\AgentVersionStatus;use App\Domain\AI\Agents\Models\{Agent,AgentContractVersion,AgentVersion};use App\Domain\AI\Channels\WhatsApp\Models\WhatsAppChannel;use App\Domain\Network\Billing\EntitlementService;use App\Domain\Network\Tenancy\Models\Tenant;use App\Domain\Network\Tenancy\TenantContext;use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
class ResolveWhatsAppChannelService{
 public function __construct(private TenantContext$context,private EntitlementService$entitlements){}
 public function resolve(string$key,bool$operational=true):WhatsAppChannel{$c=WhatsAppChannel::where('webhook_key',$key)->first();if(!$c)throw new NotFoundHttpException('channel_unavailable');$t=Tenant::findOrFail($c->tenant_id);$this->context->set($t);if(!$operational)return$c;$a=Agent::find($c->agent_id);$v=$a?AgentVersion::where('agent_id',$a->id)->whereKey($a->current_published_version_id)->first():null;$cv=$v?AgentContractVersion::with('contract')->find($v->agent_contract_version_id):null;if(!$c->enabled||!$a||!$v||$v->status!==AgentVersionStatus::Published||!$cv?->contract||(int)$cv->contract->agent_id!==(int)$a->id||!$this->entitlements->has($t,(string)config('ai.entitlement.module_code','AI_CORE')))throw new NotFoundHttpException('channel_unavailable');return$c;}
}
