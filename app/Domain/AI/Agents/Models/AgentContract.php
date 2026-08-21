<?php
namespace App\Domain\AI\Agents\Models;
use App\Domain\AI\Agents\Enums\AgentContractStatus;
use App\Domain\AI\Agents\Data\AuthorizedAiLifecycleActor;
use App\Domain\AI\Support\Exceptions\AiTenantMismatchException;
use App\Domain\AI\Tenancy\AiTenantModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany};
final class AgentContract extends AiTenantModel
{
    protected $table='ai_agent_contracts';
    protected $guarded=['*'];
    protected $casts=['status'=>AgentContractStatus::class,'activated_at'=>'datetime','suspended_at'=>'datetime','ended_at'=>'datetime'];
    protected function immutableIdentityAttributes():array{return['agent_id','created_by_user_id','status','current_accepted_version_id','activated_at','suspended_at','ended_at'];}
    protected static function booted():void
    {
        parent::booted();
        static::saving(function(self$model):void{
            if(!Agent::query()->whereKey($model->agent_id)->exists())throw new AiTenantMismatchException('Agent contract must reference an agent in the active tenant.');
            if($model->current_accepted_version_id!==null&&!AgentContractVersion::query()->where('agent_contract_id',$model->id)->whereKey($model->current_accepted_version_id)->exists())throw new AiTenantMismatchException('Current accepted version must belong to this contract.');
        });
    }
    public function agent():BelongsTo{return$this->belongsTo(Agent::class);}
    public function versions():HasMany{return$this->hasMany(AgentContractVersion::class);}
    public function creator():BelongsTo{return$this->belongsTo(User::class,'created_by_user_id');}
    public function currentAcceptedVersion():BelongsTo{return$this->belongsTo(AgentContractVersion::class,'current_accepted_version_id');}
    /** @internal Lifecycle services only. */
    public function activateWithAcceptedVersion(AuthorizedAiLifecycleActor$a,AgentContractVersion$v):void{$this->assertLifecycleActor($a);if(!in_array($this->originalAiStatus(),[AgentContractStatus::Draft->value,AgentContractStatus::Active->value],true))throw new \App\Domain\AI\Agents\Exceptions\InvalidAgentTransitionException('Only a draft or active contract can accept a version.');if($v->tenant_id!==$a->tenantId||$v->agent_contract_id!==$this->id||$v->status!==\App\Domain\AI\Agents\Enums\AgentContractVersionStatus::Accepted)throw new AiTenantMismatchException('Accepted version must belong to this contract and be accepted.');$this->persistNamedLifecycle(['status','current_accepted_version_id','activated_at','suspended_at'],function()use($v){$this->status=AgentContractStatus::Active;$this->current_accepted_version_id=$v->id;$this->activated_at??=now();$this->suspended_at=null;});}
    /** @internal Lifecycle services only. */
    public function suspendLifecycle(AuthorizedAiLifecycleActor$a):void{$this->assertLifecycleActor($a);$this->expectOriginal(AgentContractStatus::Active);$this->persistNamedLifecycle(['status','suspended_at'],function(){$this->status=AgentContractStatus::Suspended;$this->suspended_at=now();});}
    /** @internal Lifecycle services only. */
    public function resumeLifecycle(AuthorizedAiLifecycleActor$a):void{$this->assertLifecycleActor($a);$this->expectOriginal(AgentContractStatus::Suspended);$this->persistNamedLifecycle(['status','suspended_at'],function(){$this->status=AgentContractStatus::Active;$this->suspended_at=null;});}
    /** @internal Aggregate termination only. */
    public function endLifecycle(AuthorizedAiLifecycleActor$a):void{$this->assertLifecycleActor($a);if($this->originalAiStatus()===AgentContractStatus::Ended->value)throw new \App\Domain\AI\Agents\Exceptions\InvalidAgentTransitionException('Ended contract is terminal.');$this->persistNamedLifecycle(['status','current_accepted_version_id','ended_at'],function(){$this->status=AgentContractStatus::Ended;$this->current_accepted_version_id=null;$this->ended_at=now();});}
    private function expectOriginal(AgentContractStatus$s):void{if($this->originalAiStatus()!==$s->value)throw new \App\Domain\AI\Agents\Exceptions\InvalidAgentTransitionException("Contract cannot transition from {$this->originalAiStatus()}.");}
}
