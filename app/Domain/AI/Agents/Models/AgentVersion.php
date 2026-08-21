<?php
namespace App\Domain\AI\Agents\Models;
use App\Domain\AI\Agents\Enums\AgentVersionStatus;
use App\Domain\AI\Agents\Data\AuthorizedAiLifecycleActor;
use App\Domain\AI\Support\Exceptions\AiTenantMismatchException;
use App\Domain\AI\Tenancy\AiTenantModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class AgentVersion extends AiTenantModel
{
    protected $table='ai_agent_versions';
    protected $guarded=['*'];
    protected $casts=['version_number'=>'integer','status'=>AgentVersionStatus::class,'configuration'=>'array','testing_at'=>'datetime','approved_at'=>'datetime','approval_evidence'=>'array','published_at'=>'datetime','retired_at'=>'datetime'];
    protected function immutableIdentityAttributes():array{return['agent_id','agent_contract_version_id','version_number','created_by_user_id','status','configuration_hash','testing_at','approved_at','approved_by_user_id','approval_evidence','published_at','published_by_user_id','retired_at','retired_by_user_id'];}
    protected static function booted():void
    {
        parent::booted();
        static::saving(function(self$model):void{
            $agent=Agent::query()->find($model->agent_id);
            if(!$agent)throw new AiTenantMismatchException('Agent version must reference an agent in the active tenant.');
            if($model->agent_contract_version_id!==null){
                $contractVersion=AgentContractVersion::query()->with('contract')->find($model->agent_contract_version_id);
                if(!$contractVersion||!$contractVersion->contract||$contractVersion->contract->agent_id!==$agent->id)throw new AiTenantMismatchException('Agent version contract must belong to the same tenant and agent.');
            }
            if($model->exists&&$model->getRawOriginal('status')!=='draft'&&$model->isDirty(['schema_version','configuration']))throw new \App\Domain\AI\Support\Exceptions\AiImmutableAttributeException('Agent version configuration is editable only in draft.');
        });
    }
    public function agent():BelongsTo{return$this->belongsTo(Agent::class);}
    public function contractVersion():BelongsTo{return$this->belongsTo(AgentContractVersion::class,'agent_contract_version_id');}
    public function creator():BelongsTo{return$this->belongsTo(User::class,'created_by_user_id');}
    /** @internal Lifecycle services only. */
    public function markTesting(AuthorizedAiLifecycleActor$a,string$hash):void{$this->assertLifecycleActor($a);$this->expectOriginal(AgentVersionStatus::Draft);$this->assertAggregateOpen();if(!hash_equals($this->configurationHash(),$hash))throw new \App\Domain\AI\Agents\Exceptions\StaleAiContentHashException('Agent configuration hash is stale.');$this->persistNamedLifecycle(['status','configuration_hash','testing_at'],function()use($hash){$this->status=AgentVersionStatus::Testing;$this->configuration_hash=$hash;$this->testing_at=now();});}
    /** @internal Lifecycle services only. */
    public function returnToDraftLifecycle(AuthorizedAiLifecycleActor$a):void{$this->assertLifecycleActor($a);$this->expectOriginal(AgentVersionStatus::Testing);$this->assertAggregateOpen();$this->persistNamedLifecycle(['status','configuration_hash','testing_at','approved_at','approved_by_user_id','approval_evidence'],function(){$this->status=AgentVersionStatus::Draft;$this->configuration_hash=null;$this->testing_at=null;$this->approved_at=null;$this->approved_by_user_id=null;$this->approval_evidence=null;});}
    /** @internal Lifecycle services only. */
    public function markApproved(AuthorizedAiLifecycleActor$a,array$evidence):void{$this->assertLifecycleActor($a);$this->expectOriginal(AgentVersionStatus::Testing);$this->assertAggregateOpen();app(\App\Domain\AI\Agents\Support\AiLifecycleInputGuard::class)->evidence($evidence);if(!$this->configuration_hash||!hash_equals((string)$this->configuration_hash,$this->configurationHash()))throw new \App\Domain\AI\Agents\Exceptions\StaleAiContentHashException('Agent configuration hash is stale.');$this->persistNamedLifecycle(['status','approved_at','approved_by_user_id','approval_evidence'],function()use($a,$evidence){$this->status=AgentVersionStatus::Approved;$this->approved_at=now();$this->approved_by_user_id=$a->actorUserId;$this->approval_evidence=$evidence;});}
    /** @internal Lifecycle services only. */
    public function markPublished(AuthorizedAiLifecycleActor$a):void{$this->assertLifecycleActor($a);$this->expectOriginal(AgentVersionStatus::Approved);$this->assertAggregateOpen();if(!$this->configuration_hash||!hash_equals((string)$this->configuration_hash,$this->configurationHash()))throw new \App\Domain\AI\Agents\Exceptions\StaleAiContentHashException('Agent configuration hash is stale.');$agent=Agent::query()->findOrFail($this->agent_id);$contract=$agent->contract()->firstOrFail();$cv=$this->contractVersion()->first();if(!$cv||$cv->status!==\App\Domain\AI\Agents\Enums\AgentContractVersionStatus::Accepted||$contract->status!==\App\Domain\AI\Agents\Enums\AgentContractStatus::Active||$contract->current_accepted_version_id!==$cv->id||$cv->agent_contract_id!==$contract->id)throw new \App\Domain\AI\Agents\Exceptions\AgentContractNotAcceptedException('Current accepted active contract is required.');$this->persistNamedLifecycle(['status','published_at','published_by_user_id'],function()use($a){$this->status=AgentVersionStatus::Published;$this->published_at=now();$this->published_by_user_id=$a->actorUserId;});}
    /** @internal Lifecycle services only. */
    public function markRetired(AuthorizedAiLifecycleActor$a):void{$this->retireFrom($a,[AgentVersionStatus::Published]);}
    /** @internal Aggregate termination only. */
    public function markRetiredForTermination(AuthorizedAiLifecycleActor$a):void{$this->retireFrom($a,[AgentVersionStatus::Draft,AgentVersionStatus::Testing,AgentVersionStatus::Approved,AgentVersionStatus::Published]);}
    private function retireFrom(AuthorizedAiLifecycleActor$a,array$allowed):void{$this->assertLifecycleActor($a);if(!in_array($this->originalAiStatus(),array_map(fn($s)=>$s->value,$allowed),true))throw new \App\Domain\AI\Agents\Exceptions\InvalidAgentTransitionException("Agent version cannot retire from {$this->originalAiStatus()}.");$this->persistNamedLifecycle(['status','retired_at','retired_by_user_id'],function()use($a){$this->status=AgentVersionStatus::Retired;$this->retired_at=now();$this->retired_by_user_id=$a->actorUserId;});}
    private function expectOriginal(AgentVersionStatus$s):void{if($this->originalAiStatus()!==$s->value)throw new \App\Domain\AI\Agents\Exceptions\InvalidAgentTransitionException("Agent version cannot transition from {$this->originalAiStatus()}.");}
    private function assertAggregateOpen():void{$agent=Agent::query()->findOrFail($this->agent_id);$contract=$agent->contract()->firstOrFail();if($agent->status===\App\Domain\AI\Agents\Enums\AgentStatus::Retired||$contract->status===\App\Domain\AI\Agents\Enums\AgentContractStatus::Ended)throw new \App\Domain\AI\Agents\Exceptions\InvalidAgentTransitionException('Terminal aggregate cannot transition agent versions.');}
    private function configurationHash():string{return app(\App\Domain\AI\Support\CanonicalJsonHasher::class)->hash(['schema_version'=>$this->schema_version,'configuration'=>$this->configuration]);}
}
