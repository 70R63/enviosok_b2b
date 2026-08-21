<?php
namespace App\Domain\AI\Agents\Data;
use App\Domain\AI\Support\StructuredDataGuard;
use InvalidArgumentException;
final readonly class AgentContractDraftData
{
    public function __construct(
        public string$jobToBeDone,public array$objectives,public array$allowedCapabilities,public array$prohibitedCapabilities,public array$channels,
        public array$knowledgeRequirements,public array$allowedActions,public array$handoffPolicy,public array$outcomePolicy,public array$capacityPolicy,public array$slaPolicy,public array$privacyPolicy,public array$pricingPolicy,
    ){
        if(trim($jobToBeDone)==='')throw new InvalidArgumentException('Contract job_to_be_done is required.');
        foreach(['objectives'=>$objectives,'allowed_capabilities'=>$allowedCapabilities,'prohibited_capabilities'=>$prohibitedCapabilities,'channels'=>$channels,'allowed_actions'=>$allowedActions]as$name=>$list){
            if(!array_is_list($list))throw new InvalidArgumentException("Contract {$name} must be a list.");
            foreach($list as$item)if(!is_string($item)||trim($item)==='')throw new InvalidArgumentException("Contract {$name} contains an invalid item.");
        }
        if($objectives===[])throw new InvalidArgumentException('Contract objectives cannot be empty.');
        foreach(['handoff_policy'=>$handoffPolicy,'outcome_policy'=>$outcomePolicy,'capacity_policy'=>$capacityPolicy,'sla_policy'=>$slaPolicy,'privacy_policy'=>$privacyPolicy,'pricing_policy'=>$pricingPolicy]as$name=>$policy)if($policy!==[]&&array_is_list($policy))throw new InvalidArgumentException("Contract {$name} must be a structured object.");
        StructuredDataGuard::validate($this->toArray());
    }
    public function toArray():array{return['job_to_be_done'=>$this->jobToBeDone,'objectives'=>$this->objectives,'allowed_capabilities'=>$this->allowedCapabilities,'prohibited_capabilities'=>$this->prohibitedCapabilities,'channels'=>$this->channels,'knowledge_requirements'=>$this->knowledgeRequirements,'allowed_actions'=>$this->allowedActions,'handoff_policy'=>$this->handoffPolicy,'outcome_policy'=>$this->outcomePolicy,'capacity_policy'=>$this->capacityPolicy,'sla_policy'=>$this->slaPolicy,'privacy_policy'=>$this->privacyPolicy,'pricing_policy'=>$this->pricingPolicy];}
}
