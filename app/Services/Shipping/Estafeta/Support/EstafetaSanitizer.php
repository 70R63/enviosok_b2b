<?php
namespace App\Services\Shipping\Estafeta\Support;
final class EstafetaSanitizer {
 private const KEYS=['authorization','token','access_token','password','api_key','apikey','secret','email','phone','telephone','address','name'];
 public static function sanitize(mixed $value,?string $key=null): mixed { if($key&&in_array(strtolower($key),self::KEYS,true)) return '[REDACTED]'; if(is_array($value)){foreach($value as $k=>$v)$value[$k]=self::sanitize($v,(string)$k);return $value;} if(is_string($value)){ $value=preg_replace('/Bearer\s+\S+/i','Bearer [REDACTED]',$value); $value=preg_replace('/(password|api[_-]?key|token|secret)=([^&\s]+)/i','$1=[REDACTED]',$value);} return $value; }
}
