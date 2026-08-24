<?php
namespace App\Domain\AI\Channels\Webchat\Services;
use App\Domain\AI\Channels\Webchat\Models\{WebchatChannel,WebchatSession,WebchatMessageReceipt};
use App\Domain\AI\Conversations\Data\SendConversationMessageData;
use App\Domain\AI\Conversations\Models\Conversation;
use App\Domain\AI\Conversations\Services\SendInternalConversationMessageService;
use App\Models\User;
use Illuminate\Support\Facades\DB;
final class SendWebchatMessageService
{
 public function __construct(private SendInternalConversationMessageService$core){}
 public function send(WebchatChannel$channel,WebchatSession$input,string$clientId,string$message):array
 {
  if(!mb_check_encoding($message,'UTF-8')||trim($message)===''||strlen($message)>(int)config('ai.webchat.max_message_bytes',8000))throw new \InvalidArgumentException('message_invalid');
  $reserved=DB::transaction(function()use($channel,$input,$clientId){$lockedChannel=WebchatChannel::query()->whereKey($channel->id)->lockForUpdate()->firstOrFail();$session=WebchatSession::query()->where('webchat_channel_id',$lockedChannel->id)->lockForUpdate()->findOrFail($input->id);if(!$session->isAvailable()||!$lockedChannel->enabled)throw new \DomainException('channel_unavailable');$receipt=WebchatMessageReceipt::query()->where('webchat_session_id',$session->id)->where('client_message_id',$clientId)->lockForUpdate()->first();if($receipt?->status==='completed')return['duplicate',$session,$receipt];if($receipt?->status==='processing')throw new \DomainException('turn_in_progress');if($receipt?->status==='failed')throw new \RuntimeException('response_unavailable');if(!$receipt){$receipt=new WebchatMessageReceipt;$receipt->tenant_id=$session->tenant_id;$receipt->webchat_session_id=$session->id;$receipt->client_message_id=$clientId;}$receipt->status='processing';$receipt->response_sequence=null;$receipt->save();$conversation=Conversation::query()->whereKey($session->conversation_id)->lockForUpdate()->firstOrFail();if($conversation->turn_in_progress)throw new \DomainException('turn_in_progress');return['new',$session,$receipt,$conversation];});
  if($reserved[0]==='duplicate')return[$reserved[1],$reserved[1]->conversation->messages()->where('sequence',$reserved[2]->response_sequence)->firstOrFail()];
  [, $session,$receipt,$conversation]=$reserved;$actor=User::query()->findOrFail($channel->created_by_user_id);try{$result=$this->core->send($actor,$conversation,SendConversationMessageData::from($message));}catch(\Throwable$e){DB::transaction(function()use($receipt){$locked=WebchatMessageReceipt::query()->lockForUpdate()->findOrFail($receipt->id);$locked->status='failed';$locked->save();});throw$e;}
  $session=DB::transaction(function()use($session,$receipt,$clientId,$result){$locked=WebchatSession::query()->lockForUpdate()->findOrFail($session->id);$lockedReceipt=WebchatMessageReceipt::query()->lockForUpdate()->findOrFail($receipt->id);$lockedReceipt->status='completed';$lockedReceipt->response_sequence=$result->assistantMessage->sequence;$lockedReceipt->save();$locked->last_client_message_id=$clientId;$locked->last_response_sequence=$result->assistantMessage->sequence;$locked->last_seen_at=now();$locked->save();return$locked->fresh();});
  return[$session,$result->assistantMessage];
 }
}
