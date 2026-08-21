<?php
namespace App\Domain\AI\Launchpad;
use App\Domain\AI\Launchpad\Advisors\RuleBasedAgentLaunchpadAdvisor;
use App\Domain\AI\Launchpad\Contracts\AgentLaunchpadAdvisor;
use InvalidArgumentException;
final class AgentLaunchpadAdvisorRegistry
{
    public function __construct(private RuleBasedAgentLaunchpadAdvisor $rules){}
    public function resolve(?string $code=null):AgentLaunchpadAdvisor{$code??=(string)config('ai.launchpad.advisor','rules_v1');if($code!=='rules_v1')throw new InvalidArgumentException('Configured launchpad advisor is unavailable.');return $this->rules;}
}
