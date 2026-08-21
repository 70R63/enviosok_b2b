<?php

namespace App\Domain\AI\Agents\Services;

use App\Domain\AI\Agents\Data\AuthorizedAiLifecycleActor;
use App\Domain\AI\Agents\Enums\AgentLifecycleEventType;
use App\Domain\AI\Agents\Models\{Agent, AgentContract, AgentContractVersion, AgentLifecycleEvent, AgentVersion};
use App\Domain\AI\Agents\Support\AiLifecycleInputGuard;
use App\Domain\AI\Support\StructuredDataGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class AgentLifecycleAudit
{
    public function __construct(private AiLifecycleAuthorization $authorization, private AiLifecycleInputGuard $input) {}

    public function contractVersionOffered(AuthorizedAiLifecycleActor $a, Agent $agent, AgentContractVersion $v): AgentLifecycleEvent { return $this->record($a,$agent,$v,AgentLifecycleEventType::ContractVersionOffered,'draft','offered',null,['hash_verified'=>true]); }
    public function contractVersionAccepted(AuthorizedAiLifecycleActor $a, Agent $agent, AgentContractVersion $v): AgentLifecycleEvent { return $this->record($a,$agent,$v,AgentLifecycleEventType::ContractVersionAccepted,'offered','accepted',null,['hash_verified'=>true,'evidence_recorded'=>true]); }
    public function contractVersionRejected(AuthorizedAiLifecycleActor $a, Agent $agent, AgentContractVersion $v, string $reason): AgentLifecycleEvent { return $this->record($a,$agent,$v,AgentLifecycleEventType::ContractVersionRejected,'offered','rejected',$this->input->reason($reason),['hash_verified'=>true]); }
    public function contractVersionWithdrawn(AuthorizedAiLifecycleActor $a, Agent $agent, AgentContractVersion $v, string $reason): AgentLifecycleEvent { return $this->record($a,$agent,$v,AgentLifecycleEventType::ContractVersionWithdrawn,'offered','withdrawn',$this->input->reason($reason)); }
    public function contractVersionCancelled(AuthorizedAiLifecycleActor $a, Agent $agent, AgentContractVersion $v, string $reason): AgentLifecycleEvent { return $this->record($a,$agent,$v,AgentLifecycleEventType::ContractVersionCancelled,'draft','cancelled',$this->input->reason($reason)); }
    public function agentVersionTesting(AuthorizedAiLifecycleActor $a, Agent $agent, AgentVersion $v): AgentLifecycleEvent { return $this->record($a,$agent,$v,AgentLifecycleEventType::AgentVersionTesting,'draft','testing',null,['hash_verified'=>true]); }
    public function agentVersionReturnedToDraft(AuthorizedAiLifecycleActor $a, Agent $agent, AgentVersion $v, string $reason): AgentLifecycleEvent { return $this->record($a,$agent,$v,AgentLifecycleEventType::AgentVersionReturnedToDraft,'testing','draft',$this->input->reason($reason)); }
    public function agentVersionApproved(AuthorizedAiLifecycleActor $a, Agent $agent, AgentVersion $v): AgentLifecycleEvent { return $this->record($a,$agent,$v,AgentLifecycleEventType::AgentVersionApproved,'testing','approved',null,['hash_verified'=>true,'manual_review'=>true]); }
    public function agentVersionPublished(AuthorizedAiLifecycleActor $a, Agent $agent, AgentVersion $v): AgentLifecycleEvent { return $this->record($a,$agent,$v,AgentLifecycleEventType::AgentVersionPublished,'approved','published',null,['hash_verified'=>true]); }
    public function agentVersionRetired(AuthorizedAiLifecycleActor $a, Agent $agent, AgentVersion $v, string $from, string $reason): AgentLifecycleEvent { return $this->record($a,$agent,$v,AgentLifecycleEventType::AgentVersionRetired,$from,'retired',$this->input->reason($reason)); }
    public function agentPaused(AuthorizedAiLifecycleActor $a, Agent $agent, string $reason): AgentLifecycleEvent { return $this->record($a,$agent,$agent,AgentLifecycleEventType::AgentPaused,'active','paused',$this->input->reason($reason)); }
    public function agentResumed(AuthorizedAiLifecycleActor $a, Agent $agent, string $reason): AgentLifecycleEvent { return $this->record($a,$agent,$agent,AgentLifecycleEventType::AgentResumed,'paused','active',$this->input->reason($reason),['hash_verified'=>true]); }
    public function agentRetired(AuthorizedAiLifecycleActor $a, Agent $agent, string $from, string $reason): AgentLifecycleEvent { return $this->record($a,$agent,$agent,AgentLifecycleEventType::AgentRetired,$from,'retired',$this->input->reason($reason)); }
    public function contractSuspended(AuthorizedAiLifecycleActor $a, Agent $agent, AgentContract $c, string $reason): AgentLifecycleEvent { return $this->record($a,$agent,$c,AgentLifecycleEventType::ContractSuspended,'active','suspended',$this->input->reason($reason)); }
    public function contractResumed(AuthorizedAiLifecycleActor $a, Agent $agent, AgentContract $c, string $reason): AgentLifecycleEvent { return $this->record($a,$agent,$c,AgentLifecycleEventType::ContractResumed,'suspended','active',$this->input->reason($reason)); }
    public function contractEnded(AuthorizedAiLifecycleActor $a, Agent $agent, AgentContract $c, string $from, string $reason): AgentLifecycleEvent { return $this->record($a,$agent,$c,AgentLifecycleEventType::ContractEnded,$from,'ended',$this->input->reason($reason)); }

    private function record(AuthorizedAiLifecycleActor $a, Agent $aggregate, Model $resource, AgentLifecycleEventType $event, ?string $from, string $to, ?string $reason, array $metadata = []): AgentLifecycleEvent
    {
        if (DB::transactionLevel() < 1) throw new \LogicException('AI lifecycle audit requires an active transaction.');
        $a = $this->authorization->revalidate($a);
        StructuredDataGuard::validate($metadata);
        $aggregate = Agent::query()->findOrFail($aggregate->id);
        $resource = match (true) {
            $resource instanceof Agent => $aggregate,
            $resource instanceof AgentVersion => AgentVersion::query()->where('agent_id',$aggregate->id)->findOrFail($resource->id),
            $resource instanceof AgentContract => AgentContract::query()->where('agent_id',$aggregate->id)->findOrFail($resource->id),
            $resource instanceof AgentContractVersion => AgentContractVersion::query()->whereHas('contract',fn($q)=>$q->where('agent_id',$aggregate->id))->findOrFail($resource->id),
            default => throw new \InvalidArgumentException('Unsupported AI lifecycle audit resource.'),
        };
        if ((string) $resource->status->value !== $to) throw new \LogicException('Lifecycle audit state does not match persisted resource state.');
        $entry = new AgentLifecycleEvent();
        $entry->appendFromAudit($a,$aggregate,$resource,$event,$from,$to,$reason,$metadata);
        return $entry;
    }
}
