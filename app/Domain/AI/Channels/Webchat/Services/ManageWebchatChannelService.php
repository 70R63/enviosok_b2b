<?php
namespace App\Domain\AI\Channels\Webchat\Services;
use App\Domain\AI\Agents\Models\Agent;
use App\Domain\AI\Agents\Services\AiLifecycleAuthorization;
use App\Domain\AI\Channels\Webchat\Models\WebchatChannel;
use App\Domain\AI\Channels\Webchat\Support\WebchatOriginPolicy;
use App\Domain\AI\Tenancy\AiTenantBoundary;
use App\Models\User;
use Illuminate\Support\Facades\DB;
final class ManageWebchatChannelService
{
 public function __construct(private AiLifecycleAuthorization$auth,private AiTenantBoundary$tenants,private WebchatOriginPolicy$origins){}
 public function save(User$actor,Agent$input,array$data):WebchatChannel
 {
  $authorized=$this->auth->authorize($actor);$this->tenants->assertResourceBelongsToCurrentTenant($input);
  return DB::transaction(function()use($authorized,$input,$data){$this->auth->revalidate($authorized);$agent=Agent::query()->lockForUpdate()->findOrFail($input->id);$origins=array_values(array_unique(array_map(fn($v)=>$this->origins->canonicalize((string)$v,app()->environment(['local','testing'])),$data['allowed_origins'])));if(count($origins)>(int)config('ai.webchat.max_allowed_origins',20))throw new \DomainException('Too many allowed origins.');$channel=WebchatChannel::query()->where('tenant_id',$authorized->tenantId)->where('agent_id',$agent->id)->lockForUpdate()->first()??new WebchatChannel;$channel->tenant_id=$authorized->tenantId;$channel->agent_id=$agent->id;$channel->created_by_user_id??=$authorized->actorUserId;$channel->display_name=$data['display_name'];$channel->welcome_message=$data['welcome_message']??null;$channel->primary_color=strtoupper($data['primary_color']);$channel->launcher_label=$data['launcher_label'];$channel->allowed_origins=$origins;$channel->enabled=(bool)$data['enabled'];if($channel->enabled&&$agent->current_published_version_id===null)throw new \DomainException('Publish an Agent Version before enabling Webchat.');$channel->save();return$channel->fresh();});
 }
 public function rotate(User$actor,Agent$input):WebchatChannel{$authorized=$this->auth->authorize($actor);$this->tenants->assertResourceBelongsToCurrentTenant($input);return DB::transaction(function()use($authorized,$input){Agent::query()->lockForUpdate()->findOrFail($input->id);$channel=WebchatChannel::query()->where('tenant_id',$authorized->tenantId)->where('agent_id',$input->id)->lockForUpdate()->firstOrFail();DB::table('ai_webchat_channel_keys')->insert(['tenant_id'=>$channel->tenant_id,'webchat_channel_id'=>$channel->id,'public_key'=>$channel->public_key,'created_at'=>now(),'updated_at'=>now()]);$channel->public_key='wc_'.rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');$channel->save();return$channel->fresh();});}
}
