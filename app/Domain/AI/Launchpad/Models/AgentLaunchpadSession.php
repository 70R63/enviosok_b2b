<?php
namespace App\Domain\AI\Launchpad\Models;
use App\Domain\AI\Agents\Models\Agent;
use App\Domain\AI\Launchpad\Enums\LaunchpadSessionStatus;
use App\Domain\AI\Tenancy\AiTenantModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
final class AgentLaunchpadSession extends AiTenantModel
{
    protected $table='ai_agent_launchpad_sessions';protected $guarded=['*'];
    protected $casts=['status'=>LaunchpadSessionStatus::class,'intake'=>'array','recommendation'=>'array','converted_at'=>'datetime'];
    public function getRouteKeyName():string{return'uuid';}
    public function isAiAppendOnly():bool{return true;}
    protected function immutableIdentityAttributes():array{return['uuid','objective_code','intake','recommendation','advisor_code','recommendation_version','confidence','created_by_user_id','status','converted_agent_id','converted_at','converted_by_user_id'];}
    protected static function booted():void{parent::booted();static::creating(function(self$m){$m->uuid??=(string)Str::uuid();});}
    public function markConverted(Agent $agent,User $actor):void
    {if($this->status===LaunchpadSessionStatus::Converted)return;if($this->status!==LaunchpadSessionStatus::Recommended)throw new \DomainException('Only a recommended session can be converted.');$this->persistNamedLifecycle(['converted_agent_id','converted_at','converted_by_user_id','status'],function()use($agent,$actor){$this->converted_agent_id=$agent->id;$this->converted_at=now();$this->converted_by_user_id=$actor->id;$this->status=LaunchpadSessionStatus::Converted;});}
    public function creator():BelongsTo{return$this->belongsTo(User::class,'created_by_user_id');}public function convertedAgent():BelongsTo{return$this->belongsTo(Agent::class,'converted_agent_id');}
}
