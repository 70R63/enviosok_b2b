<?php
namespace App\Domain\AI\Channels\WhatsApp\Models;
use App\Domain\AI\Conversations\Models\Conversation;use Illuminate\Database\Eloquent\Model;use Illuminate\Support\Facades\Crypt;use Illuminate\Support\Str;
final class WhatsAppSession extends Model{
 protected$table='ai_whatsapp_sessions';protected$guarded=['*'];protected$casts=['service_window_ends_at'=>'datetime','closed_at'=>'datetime'];public function getRouteKeyName():string{return'uuid';}
 protected static function booted():void{static::creating(function(self$m){$m->uuid??=(string)Str::uuid();$c=WhatsAppChannel::findOrFail($m->whatsapp_channel_id);$v=Conversation::findOrFail($m->conversation_id);if((int)$c->tenant_id!==(int)$m->tenant_id||(int)$v->tenant_id!==(int)$m->tenant_id||(int)$c->agent_id!==(int)$v->agent_id)throw new \DomainException('WhatsApp Session aggregate is inconsistent.');});}
 public function setContact(string$v):void{$this->contact_hash=hash('sha256',$v);$this->contact_encrypted=Crypt::encryptString($v);}public function contact():string{return Crypt::decryptString($this->contact_encrypted);}public function setProfileName(?string$v):void{$this->profile_name_encrypted=$v===null?null:Crypt::encryptString($v);}public function insideWindow():bool{return$this->closed_at===null&&$this->service_window_ends_at?->isFuture();}
}
