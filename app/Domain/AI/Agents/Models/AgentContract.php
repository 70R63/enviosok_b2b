<?php
namespace App\Domain\AI\Agents\Models;
use App\Domain\AI\Agents\Enums\AgentContractStatus;
use App\Domain\AI\Support\Exceptions\AiTenantMismatchException;
use App\Domain\AI\Tenancy\AiTenantModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany};
final class AgentContract extends AiTenantModel
{
    protected $table='ai_agent_contracts';
    protected $guarded=['*'];
    protected $casts=['status'=>AgentContractStatus::class];
    protected function immutableIdentityAttributes():array{return['agent_id','created_by_user_id'];}
    protected static function booted():void
    {
        parent::booted();
        static::saving(function(self$model):void{
            if(!Agent::query()->whereKey($model->agent_id)->exists())throw new AiTenantMismatchException('Agent contract must reference an agent in the active tenant.');
        });
    }
    public function agent():BelongsTo{return$this->belongsTo(Agent::class);}
    public function versions():HasMany{return$this->hasMany(AgentContractVersion::class);}
    public function creator():BelongsTo{return$this->belongsTo(User::class,'created_by_user_id');}
}
