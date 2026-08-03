<?php
namespace App\Services\Shipping\Estafeta;
use App\Services\Shipping\Estafeta\Exceptions\EstafetaAuthenticationException;
use Illuminate\Support\Facades\Cache;
class EstafetaTokenManager {
 public function __construct(private EstafetaAuthClient $auth){}
 public function token(bool $force=false):string{if($force)$this->invalidate();$cached=Cache::get($this->key());if($this->usable($cached))return $cached['token'];return Cache::lock($this->key().':lock',15)->block(10,function(){ $cached=Cache::get($this->key());if($this->usable($cached))return $cached['token'];$result=$this->auth->authenticate();$token=$result->data['access_token']??null;if(!$result->success||!is_string($token)||$token==='')throw new EstafetaAuthenticationException('Estafeta no devolvió un token válido.');$reported=isset($result->data['expires_in'])?(int)$result->data['expires_in']:(int)config('zigo_estafeta.token_cache_seconds');$ttl=max(60,$reported-max(0,(int)config('zigo_estafeta.token_expiry_skew_seconds',300)));Cache::put($this->key(),['token'=>$token,'expires_at'=>time()+$ttl,'provider_expires_in'=>$reported],$ttl);return $token;});}
 public function invalidate():void{Cache::forget($this->key());}
 public function status():array{$v=Cache::get($this->key());return['cached'=>is_array($v)&&filled($v['token']??null),'remaining_seconds'=>is_array($v)?max(0,(int)($v['expires_at']??0)-time()):0,'environment'=>config('zigo_estafeta.environment')];}
 public function key():string{return'zigo:estafeta:token:'.preg_replace('/[^a-z0-9_-]/i','_',(string)config('zigo_estafeta.environment'));}
 private function usable(mixed $v):bool{return is_array($v)&&filled($v['token']??null)&&($v['expires_at']??0)>time()+60;}
}
