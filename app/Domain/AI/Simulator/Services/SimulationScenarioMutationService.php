<?php

namespace App\Domain\AI\Simulator\Services;

use App\Domain\AI\Agents\Models\Agent;
use App\Domain\AI\Agents\Services\AiLifecycleAuthorization;
use App\Domain\AI\Simulator\Models\SimulationScenario;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SimulationScenarioMutationService
{
    public function __construct(private AiLifecycleAuthorization $authorization) {}

    public function create(User $actor, Agent $inputAgent, array $attributes): SimulationScenario
    {
        $authorized = $this->authorization->authorize($actor);
        return DB::transaction(function () use ($authorized, $inputAgent, $attributes): SimulationScenario {
            $this->authorization->revalidate($authorized);
            $agent = Agent::query()->lockForUpdate()->findOrFail($inputAgent->id);
            if (SimulationScenario::query()->where('agent_id', $agent->id)->count() >= (int) config('ai.simulator.max_scenarios_per_agent', 50)) {
                throw new \DomainException('simulation_scenario_limit');
            }
            return $this->persist(new SimulationScenario, $agent, $attributes);
        }, 3);
    }

    public function update(User $actor, Agent $inputAgent, SimulationScenario $inputScenario, array $attributes): SimulationScenario
    {
        $authorized = $this->authorization->authorize($actor);
        return DB::transaction(function () use ($authorized, $inputAgent, $inputScenario, $attributes): SimulationScenario {
            $this->authorization->revalidate($authorized);
            $agent = Agent::query()->lockForUpdate()->findOrFail($inputAgent->id);
            $scenario = SimulationScenario::query()->where('agent_id', $agent->id)->lockForUpdate()->findOrFail($inputScenario->id);
            return $this->persist($scenario, $agent, $attributes);
        }, 3);
    }

    private function persist(SimulationScenario $scenario, Agent $agent, array $attributes): SimulationScenario
    {
        $scenario->agent_id = $agent->id;
        $scenario->name = $attributes['name'];
        $scenario->description = $attributes['description'] ?? null;
        $scenario->enabled = $attributes['enabled'];
        $scenario->definition = $attributes['definition'];
        $scenario->save();
        return $scenario;
    }
}
