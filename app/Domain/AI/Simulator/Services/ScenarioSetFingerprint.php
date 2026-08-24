<?php

namespace App\Domain\AI\Simulator\Services;

use App\Domain\AI\Agents\Models\Agent;
use App\Domain\AI\Simulator\Models\SimulationScenario;
use App\Domain\AI\Support\CanonicalJsonHasher;
use Illuminate\Support\Collection;

final class ScenarioSetFingerprint
{
    public function __construct(private CanonicalJsonHasher $hasher) {}

    public function forAgent(Agent $agent): string
    {
        $scenarios = SimulationScenario::query()->where('agent_id', $agent->id)->where('enabled', true)->orderBy('uuid')->get();
        return $this->forScenarios($scenarios);
    }

    public function forScenarios(Collection $scenarios): string
    {
        return $this->hasher->hash($scenarios->map(fn (SimulationScenario $scenario) => [
            'uuid' => $scenario->uuid,
            'name' => $scenario->name,
            'definition' => $scenario->definition,
        ])->all());
    }
}
