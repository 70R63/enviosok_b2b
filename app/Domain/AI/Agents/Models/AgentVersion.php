<?php
namespace App\Domain\AI\Agents\Models;
use App\Domain\AI\Agents\Enums\AgentVersionStatus;
use App\Domain\AI\Support\Exceptions\AiTenantMismatchException;
use App\Domain\AI\Tenancy\AiTenantModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class AgentVersion extends AiTenantModel
{
    protected $table='ai_agent_versions';
    protected $guarded=['*'];
    protected $casts=['version_number'=>'integer','status'=>AgentVersionStatus::class,'configuration'=>'array'];
    protected function immutableIdentityAttributes():array{return['agent_id','agent_contract_version_id','version_number','created_by_user_id'];}
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
        });
    }
    public function agent():BelongsTo{return$this->belongsTo(Agent::class);}
    public function contractVersion():BelongsTo{return$this->belongsTo(AgentContractVersion::class,'agent_contract_version_id');}
    public function creator():BelongsTo{return$this->belongsTo(User::class,'created_by_user_id');}
}
