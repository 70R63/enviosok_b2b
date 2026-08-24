<?php

namespace App\Domain\AI\Simulator\Support;

use App\Domain\AI\Actions\ActionRegistry;
use App\Domain\AI\Actions\Data\ActionDefinition;
use App\Domain\AI\Actions\Support\ActionSchemaValidator;
use App\Domain\AI\Agents\Models\AgentVersion;
use App\Domain\Network\Tenancy\Models\Tenant;

final class ScenarioDefinitionFromForm
{
    public function __construct(private ActionRegistry $actions, private ActionSchemaValidator $schemas, private SimulationScenarioDefinition $normalizer, private SimulationPayloadLimits $limits) {}

    public function build(array $input, Tenant $tenant, AgentVersion $version): array
    {
        if (array_diff(array_keys($input), ['turns','expected_handoff','expected_outcome_type','response_contains','response_not_contains','response_completed']) !== []) {
            throw new \InvalidArgumentException('Unknown scenario builder field.');
        }
        $turnsInput = $input['turns'] ?? null;
        if (! is_array($turnsInput) || ! array_is_list($turnsInput) || $turnsInput === []) throw new \InvalidArgumentException('At least one turn is required.');
        $turns=[];$results=[];$expected=[];$confirmation=false;
        foreach ($turnsInput as $turn) {
            if (! is_array($turn) || array_diff(array_keys($turn), ['message','action_key','fixture_fields','simulate_confirmation']) !== []) throw new \InvalidArgumentException('Invalid turn fields.');
            $message=$turn['message']??null;if(!is_string($message))throw new \InvalidArgumentException('Invalid turn message.');try{$this->limits->assertMessage($message);}catch(\DomainException){throw new \InvalidArgumentException('Turn message exceeds the byte limit.');}$turns[]=$message;
            $key=$turn['action_key']??'';
            if ($key === '' || $key === 'none') continue;
            if (! is_string($key)) throw new \InvalidArgumentException('Invalid Action selection.');
            $definition=$this->trusted($key,$tenant,$version);$expected[$key]=true;
            $fixture=$this->fixture($turn['fixture_fields']??[],$definition);
            try{$this->limits->assertFixture($fixture);}catch(\DomainException){throw new \InvalidArgumentException('Simulated result exceeds the byte limit.');}$this->schemas->validate($fixture,$definition->outputSchema,(int)config('ai.simulator.max_fixture_bytes',32768));$results[$key]=$fixture;
            $requested=filter_var($turn['simulate_confirmation']??false,FILTER_VALIDATE_BOOLEAN);
            if($requested&&$definition->effect->value!=='write')throw new \InvalidArgumentException('Only WRITE Actions can simulate confirmation.');
            if($definition->effect->value==='write')$confirmation=$requested;
        }
        $assertions=[];
        if (($input['response_completed']??true) !== false) $assertions[]=['type'=>'response_completed'];
        foreach(array_keys($expected)as$key)$assertions[]=['type'=>'expected_action_key','value'=>$key];
        if($expected===[])$assertions[]=['type'=>'no_action_expected'];
        $handoff=$input['expected_handoff']??'any';
        if(!in_array($handoff,['any','yes','no'],true))throw new \InvalidArgumentException('Invalid handoff expectation.');
        if($handoff==='yes')$assertions[]=['type'=>'expected_handoff'];elseif($handoff==='no')$assertions[]=['type'=>'no_handoff_expected'];
        $outcome=trim((string)($input['expected_outcome_type']??''));
        if($outcome!==''){if($outcome!=='resolved')throw new \InvalidArgumentException('Invalid outcome expectation.');$assertions[]=['type'=>'expected_outcome_type','value'=>$outcome];}
        foreach(['response_contains','response_not_contains']as$field){$value=trim((string)($input[$field]??''));if($value!=='')$assertions[]=['type'=>$field,'value'=>$value];}
        return $this->normalizer->normalize(['turns'=>$turns,'action_results'=>$results,'simulate_confirmation'=>$confirmation,'assertions'=>$assertions]);
    }

    private function trusted(string $key,Tenant $tenant,AgentVersion $version):ActionDefinition
    {
        $definition=$this->actions->find($key);
        if(!$definition||!in_array($key,$version->contractVersion?->allowed_actions??[],true)||!$this->actions->isAvailable($definition,$tenant))throw new \InvalidArgumentException('Action is not available for this Agent.');
        return $definition;
    }

    private function fixture(mixed $fields,ActionDefinition $definition):array
    {
        if(!is_array($fields)||!array_is_list($fields)||count($fields)>50)throw new \InvalidArgumentException('Invalid simulated result fields.');
        $fixture=[];$properties=$definition->outputSchema['properties']??[];
        foreach($fields as$field){if(!is_array($field)||array_keys($field)!==['property','value']||!is_string($field['property'])||!array_key_exists($field['property'],$properties)||array_key_exists($field['property'],$fixture))throw new \InvalidArgumentException('Invalid simulated result property.');$fixture[$field['property']]=$this->coerce($field['value'],$properties[$field['property']]);}
        return $fixture;
    }

    private function coerce(mixed $value,array $schema):mixed
    {
        $types=(array)($schema['type']??[]);$type=$types[0]??null;
        if($type==='string'){if(!is_string($value))throw new \InvalidArgumentException('Invalid text result.');return $value;}
        if($type==='boolean'){if(!in_array($value,[true,false,0,1,'0','1','true','false'],true))throw new \InvalidArgumentException('Invalid boolean result.');return filter_var($value,FILTER_VALIDATE_BOOLEAN);}
        if($type==='integer'){if(filter_var($value,FILTER_VALIDATE_INT)===false)throw new \InvalidArgumentException('Invalid integer result.');return(int)$value;}
        if($type==='number'){if(!is_numeric($value))throw new \InvalidArgumentException('Invalid numeric result.');return(float)$value;}
        if(in_array($type,['array','object'],true)){if(!is_string($value)||strlen($value)>32768)throw new \InvalidArgumentException('Invalid structured result.');try{$decoded=json_decode($value,true,512,JSON_THROW_ON_ERROR);}catch(\JsonException){throw new \InvalidArgumentException('Invalid structured result.');}if(!is_array($decoded))throw new \InvalidArgumentException('Invalid structured result.');return$decoded;}
        throw new \InvalidArgumentException('Unsupported result field type.');
    }
}
