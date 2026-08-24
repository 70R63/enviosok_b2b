<?php

namespace App\Domain\AI\Actions\Data;

use App\Domain\AI\Actions\Models\ActionRun;
use App\Domain\AI\Agents\Models\{Agent, AgentContractVersion, AgentVersion};
use App\Domain\AI\Conversations\Models\{Conversation, ConversationMessage};
use App\Domain\AI\Runtime\Models\RuntimeRun;

final readonly class ActionExecutionContext
{
    public function __construct(public ActionRun $actionRun, public Conversation $conversation, public ConversationMessage $sourceMessage, public RuntimeRun $runtimeRun, public Agent $agent, public AgentVersion $agentVersion, public AgentContractVersion $contractVersion) {}
}
