<?php
namespace App\Domain\AI\Runtime\Support;
use App\Domain\AI\Actions\Data\ActionDefinition;
final class ActionRuntimeOutputSchema
{
 /** @param array<int,ActionDefinition> $definitions */
 public static function schema(array$citationIds,array$definitions):array
 {
  if($definitions===[])throw new \InvalidArgumentException('Action definitions are required.');$variants=[['type'=>'null']];$seen=[];foreach($definitions as$definition){if(!$definition instanceof ActionDefinition||isset($seen[$definition->key]))throw new \InvalidArgumentException('Invalid Action definition set.');$seen[$definition->key]=true;$variants[]=['type'=>'object','additionalProperties'=>false,'required'=>['action_key','arguments'],'properties'=>['action_key'=>['type'=>'string','enum'=>[$definition->key]],'arguments'=>$definition->inputSchema]];}$schema=RuntimeOutputSchema::schema($citationIds);$schema['required'][]='action_request';$schema['properties']['action_request']=['anyOf'=>$variants];return$schema;
 }
}
