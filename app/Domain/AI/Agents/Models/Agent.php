<?php
namespace App\Domain\AI\Agents\Models;
use App\Domain\AI\Agents\Enums\{AgentStatus,AgentType};
use App\Domain\AI\Tenancy\AiTenantModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany,HasOne};
final class Agent extends AiTenantModel
{
    protected $table='ai_agents';
    protected $guarded=['*'];
    protected $casts=['type'=>AgentType::class,'status'=>AgentStatus::class];
    protected function immutableIdentityAttributes():array{return['created_by_user_id'];}
    public function creator():BelongsTo{return$this->belongsTo(User::class,'created_by_user_id');}
    public function contract():HasOne{return$this->hasOne(AgentContract::class);}
    public function versions():HasMany{return$this->hasMany(AgentVersion::class);}
}
