<?php

namespace App\Domain\AI\Agents\Services;

use App\Domain\AI\Agents\Data\{AgentConfigurationData,AgentContractDraftData};
use App\Domain\AI\Agents\Enums\{AgentContractStatus,AgentContractVersionStatus,AgentStatus,AgentVersionStatus};
use App\Domain\AI\Agents\Exceptions\AgentVersionConflictException;
use App\Domain\AI\Agents\Models\{Agent,AgentContract,AgentContractVersion,AgentVersion};
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class CreateAgentVersionService
{
    public function __construct(private AiLifecycleAuthorization $auth) {}

    public function createAgentVersion(User $actor, Agent $input, AgentConfigurationData $data, ?AgentContractVersion $contractVersion=null): AgentVersion
    {
        $authorized=$this->auth->authorize($actor); $agentId=Agent::query()->findOrFail($input->id)->id; $cvId=$contractVersion?->id;
        try {
            return DB::transaction(function() use($authorized,$agentId,$data,$cvId) {
                $authorized=$this->auth->revalidate($authorized);
                $agent=Agent::query()->lockForUpdate()->findOrFail($agentId);
                $contract=AgentContract::query()->where('agent_id',$agent->id)->lockForUpdate()->firstOrFail();
                if($agent->status===AgentStatus::Retired||$contract->status===AgentContractStatus::Ended)throw new AgentVersionConflictException('Terminal aggregate cannot create agent versions.');
                $cv=$cvId?AgentContractVersion::query()->where('agent_contract_id',$contract->id)->lockForUpdate()->findOrFail($cvId):null;
                if(AgentVersion::query()->where('agent_id',$agent->id)->whereIn('status',['draft','testing','approved'])->exists())throw new AgentVersionConflictException('An agent work version already exists.');
                $number=(int)AgentVersion::query()->where('agent_id',$agent->id)->max('version_number')+1;
                $version=new AgentVersion(); $version->agent_id=$agent->id; $version->agent_contract_version_id=$cv?->id; $version->version_number=$number; $version->status=AgentVersionStatus::Draft; $version->schema_version=$data->schemaVersion; $version->configuration=$data->configuration; $version->created_by_user_id=$authorized->actorUserId; $version->save();
                return $version;
            },3);
        } catch(QueryException $exception) { throw new AgentVersionConflictException('Concurrent agent version allocation conflicted.',0,$exception); }
    }

    public function createContractVersion(User $actor, AgentContract $input, AgentContractDraftData $data): AgentContractVersion
    {
        $authorized=$this->auth->authorize($actor); $contractInput=AgentContract::query()->findOrFail($input->id);
        try {
            return DB::transaction(function() use($authorized,$contractInput,$data) {
                $authorized=$this->auth->revalidate($authorized);
                $agent=Agent::query()->lockForUpdate()->findOrFail($contractInput->agent_id);
                $contract=AgentContract::query()->where('agent_id',$agent->id)->lockForUpdate()->findOrFail($contractInput->id);
                if($agent->status===AgentStatus::Retired||$contract->status===AgentContractStatus::Ended)throw new AgentVersionConflictException('Terminal aggregate cannot create contract versions.');
                if(AgentContractVersion::query()->where('agent_contract_id',$contract->id)->whereIn('status',['draft','offered'])->exists())throw new AgentVersionConflictException('A contract work version already exists.');
                $number=(int)AgentContractVersion::query()->where('agent_contract_id',$contract->id)->max('version_number')+1;
                $version=new AgentContractVersion(); foreach($data->toArray() as $key=>$value)$version->{$key}=$value; $version->agent_contract_id=$contract->id; $version->version_number=$number; $version->status=AgentContractVersionStatus::Draft; $version->created_by_user_id=$authorized->actorUserId; $version->save();
                return $version;
            },3);
        } catch(QueryException $exception) { throw new AgentVersionConflictException('Concurrent contract version allocation conflicted.',0,$exception); }
    }
}
