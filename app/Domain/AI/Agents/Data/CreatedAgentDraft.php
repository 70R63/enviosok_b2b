<?php
namespace App\Domain\AI\Agents\Data;
use App\Domain\AI\Agents\Models\{Agent,AgentContract,AgentContractVersion,AgentVersion};
final readonly class CreatedAgentDraft
{
    public function __construct(public Agent$agent,public AgentContract$contract,public AgentContractVersion$contractVersion,public AgentVersion$agentVersion){}
}
