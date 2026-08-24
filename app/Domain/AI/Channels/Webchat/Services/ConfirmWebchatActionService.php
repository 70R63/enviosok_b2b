<?php
namespace App\Domain\AI\Channels\Webchat\Services;
use App\Domain\AI\Actions\Enums\ActionRunStatus;
use App\Domain\AI\Actions\Models\ActionRun;
use App\Domain\AI\Actions\Services\ConfirmActionRunService;
use App\Domain\AI\Channels\Webchat\Models\{WebchatChannel,WebchatSession};
use App\Domain\AI\Conversations\Enums\{ConversationMessageRole,ConversationMessageStatus};
use App\Domain\AI\Conversations\Models\ConversationMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
final class ConfirmWebchatActionService
{
 public function __construct(private ConfirmActionRunService$core){}
 public function confirm(WebchatChannel$channel,WebchatSession$session,string$uuid):ConversationMessage
 {
  $action=ActionRun::query()->where('conversation_id',$session->conversation_id)->where('uuid',$uuid)->firstOrFail();
  $authorize=function(ActionRun$pending)use($channel,$session):void{$lockedChannel=WebchatChannel::query()->whereKey($channel->id)->lockForUpdate()->firstOrFail();$lockedSession=WebchatSession::query()->where('webchat_channel_id',$lockedChannel->id)->whereKey($session->id)->lockForUpdate()->firstOrFail();if(!$lockedChannel->enabled||!$lockedSession->isAvailable()||(int)$lockedSession->conversation_id!==(int)$pending->conversation_id||(int)$lockedSession->tenant_id!==(int)$pending->tenant_id||(int)$lockedChannel->tenant_id!==(int)$pending->tenant_id||(int)$lockedChannel->agent_id!==(int)$pending->agent_id)throw new \DomainException('confirmation_unavailable');};
  if($action->status===ActionRunStatus::Succeeded){DB::transaction(fn()=>$authorize($action));return ConversationMessage::query()->where('conversation_id',$session->conversation_id)->where('role',ConversationMessageRole::Assistant->value)->where('status',ConversationMessageStatus::Completed->value)->latest('sequence')->firstOrFail();}
  if($action->status!==ActionRunStatus::AwaitingConfirmation)throw new \DomainException('confirmation_unavailable');
  return$this->core->confirm(User::query()->findOrFail($channel->created_by_user_id),$action,$authorize);
 }
}
