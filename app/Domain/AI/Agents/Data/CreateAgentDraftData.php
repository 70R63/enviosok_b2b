<?php
namespace App\Domain\AI\Agents\Data;
use App\Domain\AI\Agents\Enums\AgentType;
final readonly class CreateAgentDraftData
{
    public function __construct(public string$code,public string$name,public ?string$description,public AgentType$type,public AgentContractDraftData$contract,public AgentConfigurationData$configuration){}
}
