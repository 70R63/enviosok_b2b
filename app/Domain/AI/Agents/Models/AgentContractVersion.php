<?php
namespace App\Domain\AI\Agents\Models;
use App\Domain\AI\Agents\Enums\AgentContractVersionStatus;
use App\Domain\AI\Agents\Data\AuthorizedAiLifecycleActor;
use App\Domain\AI\Support\Exceptions\AiTenantMismatchException;
use App\Domain\AI\Tenancy\AiTenantModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany,HasOneThrough};
final class AgentContractVersion extends AiTenantModel
{
    protected $table='ai_agent_contract_versions';
    protected $guarded=['*'];
    protected $casts=['version_number'=>'integer','status'=>AgentContractVersionStatus::class,'objectives'=>'array','allowed_capabilities'=>'array','prohibited_capabilities'=>'array','channels'=>'array','knowledge_requirements'=>'array','allowed_actions'=>'array','handoff_policy'=>'array','outcome_policy'=>'array','capacity_policy'=>'array','sla_policy'=>'array','privacy_policy'=>'array','pricing_policy'=>'array','offered_at'=>'datetime','accepted_at'=>'datetime','terminal_at'=>'datetime','acceptance_evidence'=>'array'];
    protected function immutableIdentityAttributes():array{return['agent_contract_id','version_number','created_by_user_id','status','content_hash','offered_at','offered_by_user_id','accepted_at','accepted_by_user_id','accepted_content_hash','acceptance_evidence','terminal_at','terminal_by_user_id','terminal_reason'];}
    protected static function booted():void
    {
        parent::booted();
        static::saving(function(self$model):void{
            if(!AgentContract::query()->whereKey($model->agent_contract_id)->exists())throw new AiTenantMismatchException('Contract version must reference a contract in the active tenant.');
            if($model->exists&&$model->getRawOriginal('status')!=='draft'&&$model->isDirty(['job_to_be_done','objectives','allowed_capabilities','prohibited_capabilities','channels','knowledge_requirements','allowed_actions','handoff_policy','outcome_policy','capacity_policy','sla_policy','privacy_policy','pricing_policy']))throw new \App\Domain\AI\Support\Exceptions\AiImmutableAttributeException('Offered contract content is immutable.');
        });
    }
    public function contract():BelongsTo{return$this->belongsTo(AgentContract::class,'agent_contract_id');}
    public function agent():HasOneThrough{return$this->hasOneThrough(Agent::class,AgentContract::class,'id','id','agent_contract_id','agent_id');}
    public function agentVersions():HasMany{return$this->hasMany(AgentVersion::class,'agent_contract_version_id');}
    public function creator():BelongsTo{return$this->belongsTo(User::class,'created_by_user_id');}
    /** @internal Lifecycle services only. */
    public function markOffered(AuthorizedAiLifecycleActor$a,string$hash):void{$this->assertLifecycleActor($a);$this->expectOriginal(AgentContractVersionStatus::Draft);$this->assertAggregateOpen();$this->persistNamedLifecycle(['status','content_hash','offered_at','offered_by_user_id'],function()use($a,$hash){$this->status=AgentContractVersionStatus::Offered;$this->content_hash=$hash;$this->offered_at=now();$this->offered_by_user_id=$a->actorUserId;});}
    /** @internal Lifecycle services only. */
    public function markAccepted(AuthorizedAiLifecycleActor$a,string$hash,array$evidence):void{$this->assertLifecycleActor($a);$this->expectOriginal(AgentContractVersionStatus::Offered);$this->assertAggregateOpen();app(\App\Domain\AI\Agents\Support\AiLifecycleInputGuard::class)->evidence($evidence);if(!$this->content_hash||!hash_equals((string)$this->content_hash,$hash)||!hash_equals($this->contractHash(),$hash))throw new \App\Domain\AI\Agents\Exceptions\StaleAiContentHashException('Contract content hash is stale.');$this->persistNamedLifecycle(['status','accepted_at','accepted_by_user_id','accepted_content_hash','acceptance_evidence'],function()use($a,$hash,$evidence){$this->status=AgentContractVersionStatus::Accepted;$this->accepted_at=now();$this->accepted_by_user_id=$a->actorUserId;$this->accepted_content_hash=$hash;$this->acceptance_evidence=$evidence;});}
    public function markRejected(AuthorizedAiLifecycleActor$a,string$reason):void{$this->markTerminal($a,AgentContractVersionStatus::Rejected,$reason);}
    public function markWithdrawn(AuthorizedAiLifecycleActor$a,string$reason):void{$this->markTerminal($a,AgentContractVersionStatus::Withdrawn,$reason);}
    public function markSuperseded(AuthorizedAiLifecycleActor$a,string$reason):void{$this->markTerminal($a,AgentContractVersionStatus::Superseded,$reason);}
    public function markCancelled(AuthorizedAiLifecycleActor$a,string$reason):void{$this->markTerminal($a,AgentContractVersionStatus::Cancelled,$reason);}
    private function markTerminal(AuthorizedAiLifecycleActor$a,AgentContractVersionStatus$s,string$r):void{$this->assertLifecycleActor($a);$required=match($s){AgentContractVersionStatus::Superseded=>AgentContractVersionStatus::Accepted,AgentContractVersionStatus::Cancelled=>AgentContractVersionStatus::Draft,default=>AgentContractVersionStatus::Offered};$this->expectOriginal($required);if($s!==AgentContractVersionStatus::Cancelled)$this->assertAggregateOpen();$r=app(\App\Domain\AI\Agents\Support\AiLifecycleInputGuard::class)->reason($r);$this->persistNamedLifecycle(['status','terminal_at','terminal_by_user_id','terminal_reason'],function()use($a,$s,$r){$this->status=$s;$this->terminal_at=now();$this->terminal_by_user_id=$a->actorUserId;$this->terminal_reason=$r;});}
    private function expectOriginal(AgentContractVersionStatus$s):void{if($this->originalAiStatus()!==$s->value)throw new \App\Domain\AI\Agents\Exceptions\InvalidAgentTransitionException("Contract version cannot transition from {$this->originalAiStatus()}.");}
    private function assertAggregateOpen():void{$contract=AgentContract::query()->with('agent')->findOrFail($this->agent_contract_id);if($contract->status===\App\Domain\AI\Agents\Enums\AgentContractStatus::Ended||$contract->agent?->status===\App\Domain\AI\Agents\Enums\AgentStatus::Retired)throw new \App\Domain\AI\Agents\Exceptions\InvalidAgentTransitionException('Terminal aggregate cannot transition contract versions.');}
    private function contractHash():string{return app(\App\Domain\AI\Support\CanonicalJsonHasher::class)->hash(['job_to_be_done'=>$this->job_to_be_done,'objectives'=>$this->objectives,'allowed_capabilities'=>$this->allowed_capabilities,'prohibited_capabilities'=>$this->prohibited_capabilities,'channels'=>$this->channels,'knowledge_requirements'=>$this->knowledge_requirements,'allowed_actions'=>$this->allowed_actions,'handoff_policy'=>$this->handoff_policy,'outcome_policy'=>$this->outcome_policy,'capacity_policy'=>$this->capacity_policy,'sla_policy'=>$this->sla_policy,'privacy_policy'=>$this->privacy_policy,'pricing_policy'=>$this->pricing_policy]);}
}
