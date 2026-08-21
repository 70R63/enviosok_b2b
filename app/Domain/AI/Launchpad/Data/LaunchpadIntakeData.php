<?php
namespace App\Domain\AI\Launchpad\Data;
use App\Domain\AI\Support\StructuredDataGuard;
use InvalidArgumentException;
final readonly class LaunchpadIntakeData
{
    public const OBJECTIVES=['unsure','sales','customer_service','booking','quote','custom_operational'];
    public const CHANNELS=['webchat','whatsapp','api'];
    public const KNOWLEDGE=['website','pdf','docx','faq','catalog','csv','api','manual'];
    public const HANDOFF=['when_requested','unknown_or_low_confidence','sensitive_actions','always_available'];
    private const FIELDS=['problem_description','objective_code','business_description','customer_description','desired_outcomes','requested_channels','business_hours','handoff_preference','knowledge_source_types','integration_needs','preferred_language','desired_agent_name'];
    public function __construct(public string $problemDescription,public string $objectiveCode,public ?string $businessDescription,public ?string $customerDescription,public ?string $desiredOutcomes,public array $requestedChannels,public ?string $businessHours,public ?string $handoffPreference,public array $knowledgeSourceTypes,public ?string $integrationNeeds,public string $preferredLanguage='es-MX',public ?string $desiredAgentName=null)
    {
        if(!in_array($objectiveCode,self::OBJECTIVES,true))throw new InvalidArgumentException('Objective is invalid.');
        if(trim($problemDescription)===''||mb_strlen($problemDescription)>2000)throw new InvalidArgumentException('Problem description is invalid.');
        if(!array_is_list($requestedChannels)||array_diff($requestedChannels,self::CHANNELS))throw new InvalidArgumentException('Requested channels are invalid.');
        if(!array_is_list($knowledgeSourceTypes)||array_diff($knowledgeSourceTypes,self::KNOWLEDGE))throw new InvalidArgumentException('Knowledge sources are invalid.');
        if($handoffPreference!==null&&!in_array($handoffPreference,self::HANDOFF,true))throw new InvalidArgumentException('Handoff preference is invalid.');
        foreach($this->toArray() as $value){if(is_string($value)&&preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',$value))throw new InvalidArgumentException('Control characters are not allowed.');}
        StructuredDataGuard::validate($this->toArray());
    }
    public static function fromArray(array $data):self{$unknown=array_diff(array_keys($data),self::FIELDS);if($unknown!==[])throw new InvalidArgumentException('Launchpad intake contains unsupported fields.');return new self(trim((string)($data['problem_description']??'')),trim((string)($data['objective_code']??'')),self::nullable($data['business_description']??null),self::nullable($data['customer_description']??null),self::nullable($data['desired_outcomes']??null),array_values($data['requested_channels']??[]),self::nullable($data['business_hours']??null),self::nullable($data['handoff_preference']??null),array_values($data['knowledge_source_types']??[]),self::nullable($data['integration_needs']??null),trim((string)($data['preferred_language']??'es-MX'))?:'es-MX',self::nullable($data['desired_agent_name']??null));}
    public function toArray():array{return['problem_description'=>$this->problemDescription,'objective_code'=>$this->objectiveCode,'business_description'=>$this->businessDescription,'customer_description'=>$this->customerDescription,'desired_outcomes'=>$this->desiredOutcomes,'requested_channels'=>$this->requestedChannels,'business_hours'=>$this->businessHours,'handoff_preference'=>$this->handoffPreference,'knowledge_source_types'=>$this->knowledgeSourceTypes,'integration_needs'=>$this->integrationNeeds,'preferred_language'=>$this->preferredLanguage,'desired_agent_name'=>$this->desiredAgentName];}
    private static function nullable(mixed $v):?string{$v=trim((string)$v);return $v===''?null:$v;}
}
