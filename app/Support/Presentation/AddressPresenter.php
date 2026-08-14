<?php
namespace App\Support\Presentation;
final class AddressPresenter{
 public static function full(mixed $address):string{if(is_scalar($address))return trim((string)$address)?:'—';if(!is_array($address))return'—';$groups=[['street','calle'],['number','exterior','numero'],['interior'],['colony','colonia','settlement'],['postal_code','codigo_postal','cp'],['municipality','municipio','alcaldia'],['state','estado'],['country','pais']];$parts=[];foreach($groups as$keys){foreach($keys as$key){if(array_key_exists($key,$address)&&is_scalar($address[$key])&&trim((string)$address[$key])!==''){$parts[]=trim((string)$address[$key]);break;}}}if($parts===[])foreach($address as$value)if(is_scalar($value)&&trim((string)$value)!=='')$parts[]=trim((string)$value);return self::join($parts);}
 public static function compact(mixed $address):string{if(!is_array($address))return self::full($address);return self::join(array_filter([self::first($address,['municipality','municipio','alcaldia']),self::first($address,['state','estado'])]));}
 private static function first(array$a,array$keys):?string{foreach($keys as$key)if(isset($a[$key])&&is_scalar($a[$key])&&trim((string)$a[$key])!=='')return trim((string)$a[$key]);return null;}
 private static function join(array$parts):string{$unique=[];foreach($parts as$part){$part=trim((string)$part);if($part!==''&&($unique===[]||end($unique)!==$part))$unique[]=$part;}return$unique===[]?'—':implode(', ',$unique);}
}
