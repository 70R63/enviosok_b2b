<?php

namespace App\Domain\AI\Simulator\Support;

final class SimulationScenarioDefinition
{
    public function __construct(private SimulationPayloadLimits $limits) {}

    public const ASSERTIONS = [
        'response_completed', 'expected_action_key', 'no_action_expected',
        'expected_handoff', 'no_handoff_expected', 'expected_outcome_type',
        'no_outcome_expected', 'expected_safe_error_code', 'response_contains',
        'response_not_contains',
    ];

    public function normalize(array $definition): array
    {
        $allowed = ['turns', 'prompt', 'action_results', 'simulate_confirmation', 'assertions'];
        if (array_diff(array_keys($definition), $allowed) !== []) {
            throw new \InvalidArgumentException('Unknown scenario definition field.');
        }
        $turns = $definition['turns'] ?? (isset($definition['prompt']) ? [$definition['prompt']] : []);
        if (! is_array($turns) || ! array_is_list($turns) || $turns === [] || count($turns) > $this->maxTurns()) {
            throw new \InvalidArgumentException('A scenario requires a valid turn list.');
        }
        $turns = array_map(function ($turn): string {
            if (! is_string($turn) || trim($turn) === '') {
                throw new \InvalidArgumentException('Invalid scenario turn.');
            }
            $this->limits->assertMessage($turn);
            return trim($turn);
        }, $turns);
        $results = $definition['action_results'] ?? [];
        if (! is_array($results) || ($results !== [] && array_is_list($results))) {
            throw new \InvalidArgumentException('Invalid simulated Action results.');
        }
        foreach ($results as $key => $result) {
            if (! is_string($key) || ! preg_match('/^[a-z0-9][a-z0-9_.-]{0,63}$/D', $key) || ! is_array($result) || ($result !== [] && array_is_list($result))) {
                throw new \InvalidArgumentException('Invalid simulated Action result.');
            }
            $this->limits->assertFixture($result);
        }
        $assertions = $definition['assertions'] ?? [['type' => 'response_completed']];
        if (! is_array($assertions) || ! array_is_list($assertions) || $assertions === [] || count($assertions) > 20) {
            throw new \InvalidArgumentException('Invalid scenario assertions.');
        }
        foreach ($assertions as $assertion) {
            if (! is_array($assertion) || ! is_string($assertion['type'] ?? null) || ! in_array($assertion['type'], self::ASSERTIONS, true) || array_diff(array_keys($assertion), ['type', 'value']) !== []) {
                throw new \InvalidArgumentException('Invalid scenario assertion.');
            }
            if (array_key_exists('value', $assertion) && (! is_string($assertion['value']) || mb_strlen($assertion['value']) > 500)) {
                throw new \InvalidArgumentException('Invalid scenario assertion value.');
            }
        }
        return ['turns' => $turns, 'action_results' => $results, 'simulate_confirmation' => ($definition['simulate_confirmation'] ?? false) === true, 'assertions' => $assertions];
    }

    private function maxTurns(): int
    {
        return min(25, max(1, (int) config('ai.simulator.max_turns_per_scenario', 10)));
    }
}
