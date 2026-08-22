<?php
namespace App\Domain\AI\Runtime\Support;
final class RuntimeOutputSchema
{
 public static function schema(array$citationIds):array
 {
  if($citationIds===[]||count($citationIds)>10||count($citationIds)!==count(array_unique($citationIds)))throw new \InvalidArgumentException('Invalid runtime citation schema.');
  foreach($citationIds as$id)if(!is_string($id)||!preg_match('/^K(?:10|[1-9])$/D',$id))throw new \InvalidArgumentException('Invalid runtime citation schema.');
  return['type'=>'object','additionalProperties'=>false,'required'=>['answer','citation_ids','confidence','needs_handoff','handoff_reason'],'properties'=>['answer'=>['type'=>'string','description'=>'Proposed answer grounded in supplied knowledge.'],'citation_ids'=>['type'=>'array','description'=>'Knowledge references supporting the answer.','items'=>['type'=>'string','enum'=>array_values($citationIds)]],'confidence'=>['type'=>'string','enum'=>['high','medium','low']],'needs_handoff'=>['type'=>'boolean'],'handoff_reason'=>['type'=>'string','enum'=>['none','insufficient_knowledge','low_confidence','human_requested','sensitive_action','policy_restriction']]]];
 }
}
