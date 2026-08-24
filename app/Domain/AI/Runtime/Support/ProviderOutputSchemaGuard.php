<?php
namespace App\Domain\AI\Runtime\Support;
use App\Domain\AI\Runtime\Exceptions\InvalidModelResponseException;
final class ProviderOutputSchemaGuard
{
 private const KEYWORDS=['type','properties','required','additionalProperties','items','enum','description','anyOf'];
 public static function validate(array$schema):void{self::node($schema,1,true);}
 private static function node(array$schema,int$depth,bool$root=false):void
 {
  if($depth>10)self::fail();
  foreach(array_keys($schema)as$key)if(!is_string($key)||!in_array($key,self::KEYWORDS,true))self::fail();
  if(array_key_exists('anyOf',$schema)){if($root||!is_array($schema['anyOf'])||!array_is_list($schema['anyOf'])||count($schema['anyOf'])<2||array_diff(array_keys($schema),['anyOf','description'])!==[])self::fail();foreach($schema['anyOf']as$child)if(!is_array($child)||array_is_list($child))self::fail();else self::node($child,$depth+1);return;}
  $type=$schema['type']??null;$types=is_string($type)?[$type]:(is_array($type)&&array_is_list($type)?$type:[]);
  if($types===[]||array_diff($types,['object','array','string','number','integer','boolean','null'])!==[]||count($types)!==count(array_unique($types)))self::fail();
  if($root&&!in_array('object',$types,true))self::fail();
  if(isset($schema['description'])&&!is_string($schema['description']))self::fail();
  if(array_key_exists('enum',$schema)&&(!is_array($schema['enum'])||!array_is_list($schema['enum'])||$schema['enum']===[]))self::fail();
  if(in_array('object',$types,true)){
   $properties=$schema['properties']??null;$required=$schema['required']??null;
   if(!is_array($properties)||array_is_list($properties)||!is_array($required)||!array_is_list($required)||($schema['additionalProperties']??null)!==false)self::fail();
   $propertyNames=array_keys($properties);$requiredNames=$required;sort($propertyNames);sort($requiredNames);
   if($propertyNames!==$requiredNames||count($required)!==count(array_unique($required)))self::fail();
   foreach($properties as$name=>$child)if(!is_string($name)||$name===''||!is_array($child)||array_is_list($child))self::fail();else self::node($child,$depth+1);
  }elseif(array_key_exists('properties',$schema)||array_key_exists('required',$schema)||array_key_exists('additionalProperties',$schema))self::fail();
  if(in_array('array',$types,true)){if(!isset($schema['items'])||!is_array($schema['items'])||array_is_list($schema['items']))self::fail();self::node($schema['items'],$depth+1);}elseif(array_key_exists('items',$schema))self::fail();
 }
 private static function fail():never{throw new InvalidModelResponseException('The provider output schema is incompatible.');}
}
