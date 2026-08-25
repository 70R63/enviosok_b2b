<?php
namespace App\Domain\AI\Agents\Services;
use App\Domain\AI\Agents\Data\{CreateAgentDraftData,CreatedAgentDraft};
use App\Domain\AI\Agents\Enums\{AgentContractStatus,AgentContractVersionStatus,AgentStatus,AgentVersionStatus};
use App\Domain\AI\Agents\Models\{Agent,AgentContract,AgentContractVersion,AgentVersion};
use App\Domain\AI\Core\{AiEntitlementGate,AiFeatureGate};
use App\Domain\AI\Tenancy\AiTenantBoundary;
use App\Domain\Network\Tenancy\TenantAccessService;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use App\Domain\AI\Usage\AiCapacityService;
use App\Domain\Network\Tenancy\Models\Tenant;
use InvalidArgumentException;
final class CreateAgentDraftService
{
    public function __construct(private AiFeatureGate$featureGate,private AiTenantBoundary$tenants,private AiEntitlementGate$entitlements,private TenantAccessService$access,private AiCapacityService$capacity){}
    public function create(User$actor,CreateAgentDraftData$data):CreatedAgentDraft
    {
        $this->featureGate->ensureEnabled();$this->tenants->requireTenant();$this->entitlements->ensureAllowed();
        if(!$this->access->canManageTenant($actor))throw new AuthorizationException('Only an active tenant owner or admin may create AI agents.');
        $code=strtoupper(trim($data->code));$name=trim($data->name);
        if(!preg_match('/^[A-Z0-9][A-Z0-9_-]{1,79}$/',$code))throw new InvalidArgumentException('Agent code is invalid.');
        if($name===''||mb_strlen($name)>160)throw new InvalidArgumentException('Agent name is invalid.');
        return DB::transaction(function()use($actor,$data,$code,$name):CreatedAgentDraft{
            $this->capacity->assertResourceAvailable(Tenant::query()->findOrFail($this->tenants->requireTenant()->id),AiCapacityService::MAX_AGENTS);
            $agent=new Agent();
            $agent->code=$code;$agent->name=$name;$agent->description=$data->description;$agent->type=$data->type;$agent->status=AgentStatus::Draft;$agent->created_by_user_id=$actor->id;$agent->save();
            $contract=new AgentContract();
            $contract->agent_id=$agent->id;$contract->status=AgentContractStatus::Draft;$contract->created_by_user_id=$actor->id;$contract->save();
            $contractVersion=new AgentContractVersion();
            foreach($data->contract->toArray()as$attribute=>$value)$contractVersion->{$attribute}=$value;
            $contractVersion->agent_contract_id=$contract->id;$contractVersion->version_number=1;$contractVersion->status=AgentContractVersionStatus::Draft;$contractVersion->created_by_user_id=$actor->id;$contractVersion->save();
            $agentVersion=new AgentVersion();
            $agentVersion->agent_id=$agent->id;$agentVersion->agent_contract_version_id=$contractVersion->id;$agentVersion->version_number=1;$agentVersion->status=AgentVersionStatus::Draft;$agentVersion->schema_version=$data->configuration->schemaVersion;$agentVersion->configuration=$data->configuration->configuration;$agentVersion->created_by_user_id=$actor->id;$agentVersion->save();
            return new CreatedAgentDraft($agent,$contract,$contractVersion,$agentVersion);
        });
    }
}
