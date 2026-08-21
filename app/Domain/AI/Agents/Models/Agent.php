<?php
namespace App\Domain\AI\Agents\Models;
use App\Domain\AI\Agents\Enums\{AgentStatus,AgentType};
use App\Domain\AI\Agents\Data\AuthorizedAiLifecycleActor;
use App\Domain\AI\Tenancy\AiTenantModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany,HasOne};
final class Agent extends AiTenantModel
{
    protected $table='ai_agents';
    protected $guarded=['*'];
    protected $casts=['type'=>AgentType::class,'status'=>AgentStatus::class,'activated_at'=>'datetime','paused_at'=>'datetime','retired_at'=>'datetime'];
    protected function immutableIdentityAttributes():array{return['created_by_user_id','status','current_published_version_id','activated_at','paused_at','retired_at'];}
    protected static function booted():void{parent::booted();static::saving(function(self$m):void{if($m->current_published_version_id!==null&&!AgentVersion::query()->where('agent_id',$m->id)->whereKey($m->current_published_version_id)->exists())throw new \App\Domain\AI\Support\Exceptions\AiTenantMismatchException('Current published version must belong to this agent.');});}
    public function creator():BelongsTo{return$this->belongsTo(User::class,'created_by_user_id');}
    public function contract():HasOne{return$this->hasOne(AgentContract::class);}
    public function versions():HasMany{return$this->hasMany(AgentVersion::class);}
    public function currentPublishedVersion():BelongsTo{return$this->belongsTo(AgentVersion::class,'current_published_version_id');}
    /** @internal Lifecycle services only. */
    public function activateWithPublishedVersion(AuthorizedAiLifecycleActor$a,AgentVersion$v):void{$this->assertLifecycleActor($a);if(!in_array($this->originalAiStatus(),[AgentStatus::Draft->value,AgentStatus::Paused->value,AgentStatus::Active->value],true))throw new \App\Domain\AI\Agents\Exceptions\InvalidAgentTransitionException('Only a draft, paused or active agent can activate a published version.');if($v->tenant_id!==$a->tenantId||$v->agent_id!==$this->id||$v->status!==\App\Domain\AI\Agents\Enums\AgentVersionStatus::Published)throw new \App\Domain\AI\Support\Exceptions\AiTenantMismatchException('Published version must belong to this agent and be published.');$this->persistNamedLifecycle(['status','current_published_version_id','activated_at','paused_at'],function()use($v){$this->status=AgentStatus::Active;$this->current_published_version_id=$v->id;$this->activated_at??=now();$this->paused_at=null;});}
    /** @internal Lifecycle services only. */
    public function pauseLifecycle(AuthorizedAiLifecycleActor$a):void{$this->assertLifecycleActor($a);$this->expectOriginal(AgentStatus::Active);$this->persistNamedLifecycle(['status','paused_at'],function(){$this->status=AgentStatus::Paused;$this->paused_at=now();});}
    /** @internal Lifecycle services only. */
    public function pauseAndClearPublishedVersion(AuthorizedAiLifecycleActor$a):void{$this->assertLifecycleActor($a);$this->expectOriginal(AgentStatus::Active);$this->persistNamedLifecycle(['status','paused_at','current_published_version_id'],function(){$this->status=AgentStatus::Paused;$this->paused_at=now();$this->current_published_version_id=null;});}
    /** @internal Lifecycle services only. */
    public function resumeLifecycle(AuthorizedAiLifecycleActor$a):void{$this->assertLifecycleActor($a);$this->expectOriginal(AgentStatus::Paused);$contract=$this->contract()->firstOrFail();$version=$this->currentPublishedVersion()->first();$cv=$contract->currentAcceptedVersion()->first();if($contract->status!==\App\Domain\AI\Agents\Enums\AgentContractStatus::Active||!$version||$version->status!==\App\Domain\AI\Agents\Enums\AgentVersionStatus::Published||!$cv||$cv->status!==\App\Domain\AI\Agents\Enums\AgentContractVersionStatus::Accepted||$version->agent_id!==$this->id||$version->agent_contract_version_id!==$cv->id||$cv->agent_contract_id!==$contract->id)throw new \App\Domain\AI\Agents\Exceptions\AgentContractNotAcceptedException('Complete current published and accepted chain is required.');$hasher=app(\App\Domain\AI\Support\CanonicalJsonHasher::class);$vh=$hasher->hash(['schema_version'=>$version->schema_version,'configuration'=>$version->configuration]);$ch=$hasher->hash(['job_to_be_done'=>$cv->job_to_be_done,'objectives'=>$cv->objectives,'allowed_capabilities'=>$cv->allowed_capabilities,'prohibited_capabilities'=>$cv->prohibited_capabilities,'channels'=>$cv->channels,'knowledge_requirements'=>$cv->knowledge_requirements,'allowed_actions'=>$cv->allowed_actions,'handoff_policy'=>$cv->handoff_policy,'outcome_policy'=>$cv->outcome_policy,'capacity_policy'=>$cv->capacity_policy,'sla_policy'=>$cv->sla_policy,'privacy_policy'=>$cv->privacy_policy,'pricing_policy'=>$cv->pricing_policy]);if(!hash_equals((string)$version->configuration_hash,$vh)||!hash_equals((string)$cv->content_hash,$ch)||!hash_equals((string)$cv->accepted_content_hash,$ch))throw new \App\Domain\AI\Agents\Exceptions\StaleAiContentHashException('Lifecycle content hash is stale.');$this->persistNamedLifecycle(['status','paused_at'],function(){$this->status=AgentStatus::Active;$this->paused_at=null;});}
    /** @internal Aggregate termination only. */
    public function retireLifecycle(AuthorizedAiLifecycleActor$a):void{$this->assertLifecycleActor($a);if($this->originalAiStatus()===AgentStatus::Retired->value)throw new \App\Domain\AI\Agents\Exceptions\InvalidAgentTransitionException('Retired agent is terminal.');$this->persistNamedLifecycle(['status','current_published_version_id','retired_at'],function(){$this->status=AgentStatus::Retired;$this->current_published_version_id=null;$this->retired_at=now();});}
    private function expectOriginal(AgentStatus$s):void{if($this->originalAiStatus()!==$s->value)throw new \App\Domain\AI\Agents\Exceptions\InvalidAgentTransitionException("Agent cannot transition from {$this->originalAiStatus()}.");}
}
