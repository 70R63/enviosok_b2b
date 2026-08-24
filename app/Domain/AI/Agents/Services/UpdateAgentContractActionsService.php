<?php
namespace App\Domain\AI\Agents\Services;
use App\Domain\AI\Actions\ActionRegistry;use App\Domain\AI\Agents\Enums\AgentContractVersionStatus;use App\Domain\AI\Agents\Models\{Agent,AgentContractVersion};use App\Domain\Network\Tenancy\Models\Tenant;use App\Models\User;use Illuminate\Support\Facades\DB;
final class UpdateAgentContractActionsService
{
 public function __construct(private AiLifecycleAuthorization$authorization,private ActionRegistry$registry){}
 public function update(User$actor,Agent$agent,array$selected):AgentContractVersion
 {
  $authorized=$this->authorization->authorize($actor);return DB::transaction(function()use($authorized,$agent,$selected){$fresh=$this->authorization->revalidate($authorized);$locked=Agent::query()->whereKey($agent->id)->lockForUpdate()->firstOrFail();$version=AgentContractVersion::query()->whereHas('contract',fn($q)=>$q->where('agent_id',$locked->id))->where('status',AgentContractVersionStatus::Draft->value)->lockForUpdate()->firstOrFail();$tenant=Tenant::query()->findOrFail($fresh->tenantId);$available=$this->registry->availableFor($tenant);$selected=array_values(array_unique($selected));if(array_diff($selected,array_keys($available))!==[])throw new \DomainException('One or more Actions are unavailable.');sort($selected);$version->allowed_actions=$selected;$version->save();return$version->fresh();});
 }
}
