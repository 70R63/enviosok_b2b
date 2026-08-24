<?php
namespace App\Domain\AI\Actions\Services;
use App\Domain\AI\Agents\Models\{AgentContractVersion,AgentVersion};
use App\Domain\AI\Conversations\Enums\{ConversationMessageRole,ConversationMessageStatus};
use App\Domain\AI\Conversations\Models\{Conversation,ConversationMessage};
use App\Domain\AI\Runtime\Enums\RuntimeRunStatus;
use App\Domain\AI\Runtime\Models\RuntimeRun;
final class ActionAggregateGuard
{
 public function assert(Conversation$c,ConversationMessage$m,RuntimeRun$r,AgentVersion$v,AgentContractVersion$cv):void
 {
  if($m->conversation_id!==$c->id||$m->role!==ConversationMessageRole::User||$m->status!==ConversationMessageStatus::Completed||$m->runtime_run_id!==$r->id||$r->status!==RuntimeRunStatus::Completed||$r->agent_id!==$c->agent_id||$r->agent_version_id!==$c->agent_version_id||$v->id!==$c->agent_version_id||$v->agent_id!==$c->agent_id||$v->agent_contract_version_id!==$cv->id||$cv->contract()->where('agent_id',$c->agent_id)->doesntExist())throw new \DomainException('The Action aggregate is inconsistent.');
 }
}
