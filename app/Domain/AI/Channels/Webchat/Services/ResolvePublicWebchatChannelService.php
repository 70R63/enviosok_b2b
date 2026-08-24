<?php
namespace App\Domain\AI\Channels\Webchat\Services;
use App\Domain\AI\Agents\Enums\AgentVersionStatus;
use App\Domain\AI\Agents\Models\{Agent,AgentContractVersion,AgentVersion};
use App\Domain\AI\Channels\Webchat\Models\WebchatChannel;
use App\Domain\Network\Billing\EntitlementService;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Network\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
final class ResolvePublicWebchatChannelService
{
 public function __construct(private TenantContext$context,private EntitlementService$entitlements){}
 public function resolve(string$key,bool$allowRetiredKey=false):WebchatChannel
 {
  $channel=WebchatChannel::query()->where('public_key',$key)->first();
  if(!$channel&&$allowRetiredKey){$id=DB::table('ai_webchat_channel_keys')->where('public_key',$key)->value('webchat_channel_id');$channel=$id?WebchatChannel::query()->find($id):null;}
  if(!$channel)throw new NotFoundHttpException('channel_unavailable');
  $tenant=Tenant::query()->findOrFail($channel->tenant_id);$this->context->set($tenant);
  $agent=Agent::query()->whereKey($channel->agent_id)->first();$version=$agent?AgentVersion::query()->where('agent_id',$agent->id)->whereKey($agent->current_published_version_id)->first():null;$contractVersion=$version?AgentContractVersion::query()->whereKey($version->agent_contract_version_id)->with('contract')->first():null;
  if(!$channel->enabled||!$agent||!$version||$version->status!==AgentVersionStatus::Published||!$contractVersion||!$contractVersion->contract||(int)$contractVersion->contract->agent_id!==(int)$agent->id||!$this->entitlements->has($tenant,(string)config('ai.entitlement.module_code','AI_CORE')))throw new NotFoundHttpException('channel_unavailable');
  return$channel;
 }
}
