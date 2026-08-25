<?php
namespace App\Domain\AI\Channels\WhatsApp\Services;
use App\Domain\AI\Actions\Models\ActionRun;use App\Domain\AI\Actions\Services\PresentActionConfirmationService;use App\Domain\AI\Channels\WhatsApp\Contracts\WhatsAppProvider;use App\Domain\AI\Channels\WhatsApp\Data\{WhatsAppOutboundConfirmationRequest,WhatsAppOutboundTextRequest};use App\Domain\AI\Channels\WhatsApp\Models\{WhatsAppChannel,WhatsAppDelivery,WhatsAppSession};use App\Domain\AI\Conversations\Models\ConversationMessage;use Illuminate\Support\Facades\DB;
final class WhatsAppDeliveryService{
 public function __construct(private WhatsAppProvider$provider,private PresentActionConfirmationService$presenter){}
 public function text(WhatsAppChannel$c,WhatsAppSession$s,ConversationMessage$m):WhatsAppDelivery{return$this->deliver($c,$s,$m,null,'text',fn($key)=>$this->provider->sendText(new WhatsAppOutboundTextRequest($c->id,$s->contact(),$m->content,$key)));}
 public function confirmation(WhatsAppChannel$c,WhatsAppSession$s,ConversationMessage$m,ActionRun$a):WhatsAppDelivery{$plain=rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');return$this->deliver($c,$s,$m,$a,'confirmation',fn($key)=>$this->provider->sendConfirmation(new WhatsAppOutboundConfirmationRequest($c->id,$s->contact(),$this->presenter->present($a),'confirm_'.$plain,'cancel_'.$plain,$key)),hash('sha256',$plain));}
 private function deliver(WhatsAppChannel$c,WhatsAppSession$s,ConversationMessage$m,?ActionRun$a,string$kind,callable$send,?string$tokenHash=null):WhatsAppDelivery{
  [$d,$created]=DB::transaction(function()use($c,$s,$m,$a,$kind,$tokenHash){
   $channel=WhatsAppChannel::lockForUpdate()->findOrFail($c->id);$session=WhatsAppSession::where('whatsapp_channel_id',$channel->id)->lockForUpdate()->findOrFail($s->id);
   if(!$channel->enabled||!$session->insideWindow())throw new \DomainException('delivery_unavailable');$key=hash('sha256',$session->id.':'.$m->id.':'.$kind);$existing=WhatsAppDelivery::where('idempotency_key',$key)->lockForUpdate()->first();
   if($existing){if($existing->status==='processing'){$existing->status='ambiguous';$existing->safe_error_code='provider_delivery_ambiguous';$existing->save();}return[$existing,false];}
   $x=new WhatsAppDelivery;$x->tenant_id=$session->tenant_id;$x->whatsapp_session_id=$session->id;$x->conversation_message_id=$m->id;$x->action_run_id=$a?->id;$x->kind=$kind;$x->status='processing';$x->idempotency_key=$key;$x->confirmation_token_hash=$tokenHash;$x->confirmation_expires_at=$tokenHash?now()->addSeconds((int)config('ai.whatsapp.confirmation_ttl_seconds',900)):null;$x->save();return[$x,true];
  });
  if(!$created)return$d->fresh();try{$providerId=$send($d->idempotency_key);DB::transaction(function()use($d,$providerId){$x=WhatsAppDelivery::lockForUpdate()->findOrFail($d->id);if($x->status==='processing'){$x->status='sent';$x->provider_message_id=$providerId;$x->save();}});}catch(\Throwable){DB::transaction(function()use($d){$x=WhatsAppDelivery::lockForUpdate()->findOrFail($d->id);if($x->status==='processing'){$x->status='ambiguous';$x->safe_error_code='provider_delivery_ambiguous';$x->save();}});}return$d->fresh();
 }
}
