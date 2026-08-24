<?php
namespace App\Domain\AI\Actions\Support;
final class SafeConfirmationSummary
{
 public function validate(array$summary):array
 {
  if(array_keys($summary)!==['title','fields']||!is_string($summary['title'])||trim($summary['title'])===''||mb_strlen($summary['title'])>100||!is_array($summary['fields'])||count($summary['fields'])<1||count($summary['fields'])>10)throw new \DomainException('Invalid public confirmation summary.');
  $fields=[];foreach($summary['fields']as$field){if(!is_array($field)||array_keys($field)!==['label','value']||!is_string($field['label'])||!is_string($field['value'])||trim($field['label'])===''||trim($field['value'])===''||mb_strlen($field['label'])>80||mb_strlen($field['value'])>160||preg_match('/[\x00-\x1F\x7F]/u',$field['label'].$field['value']))throw new \DomainException('Invalid public confirmation summary.');$fields[]=['label'=>trim($field['label']),'value'=>trim($field['value'])];}
  return['title'=>trim($summary['title']),'fields'=>$fields];
 }
}
