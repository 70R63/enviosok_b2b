<?php
namespace App\Domain\AI\Channels\WhatsApp\Models;
use App\Domain\AI\Agents\Models\Agent;use Illuminate\Database\Eloquent\Model;use Illuminate\Support\Facades\Crypt;use Illuminate\Support\Str;
final class WhatsAppChannel extends Model{
 protected$table='ai_whatsapp_channels';protected$guarded=['*'];protected$hidden=['access_token_encrypted','app_secret_encrypted','verify_token_encrypted'];protected$casts=['enabled'=>'boolean'];public function getRouteKeyName():string{return'uuid';}
 protected static function booted():void{static::creating(function(self$m){$m->uuid??=(string)Str::uuid();$m->webhook_key??='wa_'.rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');$a=Agent::withoutGlobalScopes()->findOrFail($m->agent_id);if((int)$a->tenant_id!==(int)$m->tenant_id)throw new \DomainException('WhatsApp Channel aggregate is inconsistent.');});}
 public function setAccessToken(string$v):void{$this->access_token_encrypted=Crypt::encryptString($v);}public function accessToken():string{return Crypt::decryptString($this->access_token_encrypted);}public function setAppSecret(string$v):void{$this->app_secret_encrypted=Crypt::encryptString($v);}public function appSecret():string{return Crypt::decryptString($this->app_secret_encrypted);}public function setVerifyToken(string$v):void{$this->verify_token_encrypted=Crypt::encryptString($v);}public function verifyToken():string{return Crypt::decryptString($this->verify_token_encrypted);}
}
