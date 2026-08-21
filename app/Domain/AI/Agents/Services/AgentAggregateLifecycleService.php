<?php

namespace App\Domain\AI\Agents\Services;

use App\Domain\AI\Agents\Data\AuthorizedAiLifecycleActor;
use App\Domain\AI\Agents\Enums\{AgentContractStatus, AgentContractVersionStatus, AgentStatus, AgentVersionStatus};
use App\Domain\AI\Agents\Exceptions\{AgentContractNotAcceptedException, InvalidAgentTransitionException, StaleAiContentHashException};
use App\Domain\AI\Agents\Models\{Agent, AgentContract, AgentContractVersion, AgentVersion};
use App\Domain\AI\Agents\Support\AiLifecycleInputGuard;
use App\Domain\AI\Support\CanonicalJsonHasher;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class AgentAggregateLifecycleService
{
    public function __construct(private AiLifecycleAuthorization $auth, private AiLifecycleInputGuard $input, private CanonicalJsonHasher $hasher, private AgentLifecycleAudit $audit) {}

    public function pause(User $actor, Agent $input, string $reason): Agent
    {
        $authorized=$this->auth->authorize($actor); $reason=$this->input->reason($reason);
        return DB::transaction(function() use($authorized,$input,$reason) { $authorized=$this->auth->revalidate($authorized); [$agent]=$this->lockAgentContract($input->id); if($agent->status!==AgentStatus::Active)throw new InvalidAgentTransitionException('Only an active agent can pause.'); $agent->pauseLifecycle($authorized); $this->audit->agentPaused($authorized,$agent,$reason); return $agent; },3);
    }

    public function resume(User $actor, Agent $input, string $reason): Agent
    {
        $authorized=$this->auth->authorize($actor); $reason=$this->input->reason($reason);
        return DB::transaction(function() use($authorized,$input,$reason) { $authorized=$this->auth->revalidate($authorized); [$agent,$contract]=$this->lockAgentContract($input->id); if($agent->status!==AgentStatus::Paused)throw new InvalidAgentTransitionException('Only a paused agent can resume.'); if($contract->status!==AgentContractStatus::Active||!$agent->current_published_version_id||!$contract->current_accepted_version_id)throw new AgentContractNotAcceptedException('Active accepted contract and published version are required.'); $cv=AgentContractVersion::query()->where('agent_contract_id',$contract->id)->lockForUpdate()->findOrFail($contract->current_accepted_version_id); $version=AgentVersion::query()->where('agent_id',$agent->id)->where('agent_contract_version_id',$cv->id)->lockForUpdate()->findOrFail($agent->current_published_version_id); if($version->status!==AgentVersionStatus::Published||$cv->status!==AgentContractVersionStatus::Accepted)throw new AgentContractNotAcceptedException('Published version and accepted contract are required.'); if(!hash_equals((string)$version->configuration_hash,$this->configurationHash($version))||!hash_equals((string)$cv->accepted_content_hash,$this->contractHash($cv))||!hash_equals((string)$cv->content_hash,(string)$cv->accepted_content_hash))throw new StaleAiContentHashException('Lifecycle content hash is stale.'); $agent->resumeLifecycle($authorized); $this->audit->agentResumed($authorized,$agent,$reason); return $agent; },3);
    }

    public function retire(User $actor, Agent $input, string $reason): Agent { return $this->terminate($this->auth->authorize($actor),$input->id,$this->input->reason($reason)); }

    public function suspendContract(User $actor, AgentContract $input, string $reason): AgentContract
    {
        $authorized=$this->auth->authorize($actor); $reason=$this->input->reason($reason); $agentId=AgentContract::query()->findOrFail($input->id)->agent_id;
        return DB::transaction(function() use($authorized,$agentId,$input,$reason) { $authorized=$this->auth->revalidate($authorized); [$agent,$contract]=$this->lockAgentContract($agentId,$input->id); if($contract->status!==AgentContractStatus::Active)throw new InvalidAgentTransitionException('Only an active contract can be suspended.'); $wasActive=$agent->status===AgentStatus::Active; $contract->suspendLifecycle($authorized); if($wasActive)$agent->pauseLifecycle($authorized); $this->audit->contractSuspended($authorized,$agent,$contract,$reason); if($wasActive)$this->audit->agentPaused($authorized,$agent,$reason); return $contract; },3);
    }

    public function resumeContract(User $actor, AgentContract $input, string $reason): AgentContract
    {
        $authorized=$this->auth->authorize($actor); $reason=$this->input->reason($reason); $agentId=AgentContract::query()->findOrFail($input->id)->agent_id;
        return DB::transaction(function() use($authorized,$agentId,$input,$reason) { $authorized=$this->auth->revalidate($authorized); [$agent,$contract]=$this->lockAgentContract($agentId,$input->id); if($contract->status!==AgentContractStatus::Suspended)throw new InvalidAgentTransitionException('Only a suspended contract can resume.'); $contract->resumeLifecycle($authorized); $this->audit->contractResumed($authorized,$agent,$contract,$reason); return $contract; },3);
    }

    public function endContract(User $actor, AgentContract $input, string $reason): AgentContract { $authorized=$this->auth->authorize($actor); $contract=AgentContract::query()->findOrFail($input->id); return $this->terminate($authorized,$contract->agent_id,$this->input->reason($reason),$contract->id)->contract->refresh(); }

    private function terminate(AuthorizedAiLifecycleActor $authorization, int $agentId, string $reason, ?int $contractId=null): Agent
    {
        return DB::transaction(function() use($authorization,$agentId,$reason,$contractId) {
            $authorization=$this->auth->revalidate($authorization); [$agent,$contract]=$this->lockAgentContract($agentId,$contractId);
            if($agent->status===AgentStatus::Retired&&$contract->status===AgentContractStatus::Ended)throw new InvalidAgentTransitionException('Aggregate is already terminal.');
            $agentFrom=$agent->status->value; $contractFrom=$contract->status->value;
            $contractVersions=AgentContractVersion::query()->where('agent_contract_id',$contract->id)->orderBy('id')->lockForUpdate()->get();
            $agentVersions=AgentVersion::query()->where('agent_id',$agent->id)->orderBy('id')->lockForUpdate()->get();
            foreach($contractVersions as $version){ if($version->status===AgentContractVersionStatus::Draft){$version->markCancelled($authorization,$reason);$this->audit->contractVersionCancelled($authorization,$agent,$version,$reason);}elseif($version->status===AgentContractVersionStatus::Offered){$version->markWithdrawn($authorization,$reason);$this->audit->contractVersionWithdrawn($authorization,$agent,$version,$reason);} }
            foreach($agentVersions as $version){ if(in_array($version->status,[AgentVersionStatus::Draft,AgentVersionStatus::Testing,AgentVersionStatus::Approved,AgentVersionStatus::Published],true)){ $from=$version->status->value; $version->markRetiredForTermination($authorization); $this->audit->agentVersionRetired($authorization,$agent,$version,$from,$reason); } }
            if($contract->status!==AgentContractStatus::Ended){$contract->endLifecycle($authorization);$this->audit->contractEnded($authorization,$agent,$contract,$contractFrom,$reason);}
            if($agent->status!==AgentStatus::Retired){$agent->retireLifecycle($authorization);$this->audit->agentRetired($authorization,$agent,$agentFrom,$reason);}
            return $agent;
        },3);
    }

    private function lockAgentContract(int $agentId, ?int $contractId=null): array { $agent=Agent::query()->lockForUpdate()->findOrFail($agentId); $query=AgentContract::query()->where('agent_id',$agent->id)->lockForUpdate(); return [$agent,$contractId?$query->findOrFail($contractId):$query->firstOrFail()]; }
    private function configurationHash(AgentVersion $v): string { return $this->hasher->hash(['schema_version'=>$v->schema_version,'configuration'=>$v->configuration]); }
    private function contractHash(AgentContractVersion $v): string { return $this->hasher->hash(['job_to_be_done'=>$v->job_to_be_done,'objectives'=>$v->objectives,'allowed_capabilities'=>$v->allowed_capabilities,'prohibited_capabilities'=>$v->prohibited_capabilities,'channels'=>$v->channels,'knowledge_requirements'=>$v->knowledge_requirements,'allowed_actions'=>$v->allowed_actions,'handoff_policy'=>$v->handoff_policy,'outcome_policy'=>$v->outcome_policy,'capacity_policy'=>$v->capacity_policy,'sla_policy'=>$v->sla_policy,'privacy_policy'=>$v->privacy_policy,'pricing_policy'=>$v->pricing_policy]); }
}
