<?php
namespace App\Domain\AI\Agents\Models;
use App\Domain\AI\Agents\Enums\AgentContractVersionStatus;
use App\Domain\AI\Support\Exceptions\AiTenantMismatchException;
use App\Domain\AI\Tenancy\AiTenantModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany,HasOneThrough};
final class AgentContractVersion extends AiTenantModel
{
    protected $table='ai_agent_contract_versions';
    protected $guarded=['*'];
    protected $casts=['version_number'=>'integer','status'=>AgentContractVersionStatus::class,'objectives'=>'array','allowed_capabilities'=>'array','prohibited_capabilities'=>'array','channels'=>'array','knowledge_requirements'=>'array','allowed_actions'=>'array','handoff_policy'=>'array','outcome_policy'=>'array','capacity_policy'=>'array','sla_policy'=>'array','privacy_policy'=>'array','pricing_policy'=>'array'];
    protected function immutableIdentityAttributes():array{return['agent_contract_id','version_number','created_by_user_id'];}
    protected static function booted():void
    {
        parent::booted();
        static::saving(function(self$model):void{
            if(!AgentContract::query()->whereKey($model->agent_contract_id)->exists())throw new AiTenantMismatchException('Contract version must reference a contract in the active tenant.');
        });
    }
    public function contract():BelongsTo{return$this->belongsTo(AgentContract::class,'agent_contract_id');}
    public function agent():HasOneThrough{return$this->hasOneThrough(Agent::class,AgentContract::class,'id','id','agent_contract_id','agent_id');}
    public function agentVersions():HasMany{return$this->hasMany(AgentVersion::class,'agent_contract_version_id');}
    public function creator():BelongsTo{return$this->belongsTo(User::class,'created_by_user_id');}
}
