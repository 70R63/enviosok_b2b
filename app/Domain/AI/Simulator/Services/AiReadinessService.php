<?php

namespace App\Domain\AI\Simulator\Services;

use App\Domain\AI\Actions\ActionRegistry;
use App\Domain\AI\Agents\Enums\{AgentContractStatus, AgentContractVersionStatus, AgentStatus, AgentVersionStatus};
use App\Domain\AI\Agents\Models\{Agent, AgentVersion};
use App\Domain\AI\Providers\ProviderRegistry;
use App\Domain\AI\Simulator\Data\ReadinessReport;
use App\Domain\AI\Simulator\Enums\SimulationRunStatus;
use App\Domain\AI\Simulator\Models\{SimulationRun, SimulationScenario};
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\AI\Support\CanonicalJsonHasher;
use Illuminate\Support\Facades\DB;

final class AiReadinessService
{
    public function __construct(private ScenarioSetFingerprint $fingerprints, private ActionRegistry $actions, private ProviderRegistry $providers, private CanonicalJsonHasher $hasher) {}

    public function evaluate(Agent $agent, AgentVersion $version): ReadinessReport
    {
        return $this->assess($agent, $version, false);
    }

    public function evaluateLocked(Agent $agent, AgentVersion $version): ReadinessReport
    {
        if (DB::transactionLevel() < 1) throw new \LogicException('Locked readiness requires a transaction.');
        return $this->assess($agent, $version, true);
    }

    private function assess(Agent $agent, AgentVersion $version, bool $lock): ReadinessReport
    {
        $agent->loadMissing('contract');
        $version->loadMissing('contractVersion.contract');
        $contractVersion = $version->contractVersion;
        $tenant = Tenant::query()->find($agent->tenant_id);
        $allowed = array_values(array_unique($contractVersion?->allowed_actions ?? []));
        $definitionsExist = collect($allowed)->every(fn (string $key) => $this->actions->find($key) !== null);
        $entitled = $tenant && collect($allowed)->every(function (string $key) use ($tenant): bool {
            $definition = $this->actions->find($key);
            return $definition !== null && $this->actions->isAvailable($definition, $tenant);
        });
        $scenarioQuery = SimulationScenario::query()->where('agent_id', $agent->id)->where('enabled', true)->orderBy('uuid');
        if ($lock) $scenarioQuery->lockForUpdate();
        $scenarios = $scenarioQuery->get();
        $enabledCount = $scenarios->count();
        $runQuery = SimulationRun::query()->where('agent_id', $agent->id)->latest('id');
        if ($lock) $runQuery->lockForUpdate();
        $latest = $runQuery->first();
        if ($latest) {
            $caseQuery = $latest->cases()->orderBy('id');
            if ($lock) $caseQuery->lockForUpdate();
            $latest->setRelation('cases', $caseQuery->get());
        }
        $fingerprintCurrent = $latest && $enabledCount > 0 && hash_equals($latest->scenario_set_fingerprint, $this->fingerprints->forScenarios($scenarios));
        $registryKeys = $this->registryKeys($allowed);
        $checks = [
            'agent_valid' => $agent->status !== AgentStatus::Retired,
            'candidate_version_valid' => $version->agent_id === $agent->id && $version->status === AgentVersionStatus::Approved,
            'exact_contract_version' => $contractVersion !== null && $contractVersion->contract?->agent_id === $agent->id && $contractVersion->status === AgentContractVersionStatus::Accepted && $agent->contract?->status === AgentContractStatus::Active && $agent->contract?->current_accepted_version_id === $contractVersion->id,
            'runtime_config_valid' => $this->providers->modelConfigured(),
            'action_allowlist_coherent' => count($allowed) === count(array_filter($allowed, fn ($key) => is_string($key) && preg_match('/^[a-z0-9][a-z0-9_.-]{0,63}$/D', $key))),
            'actions_registered' => $definitionsExist,
            'entitlements_current' => (bool) $entitled,
            'enabled_scenarios' => $enabledCount > 0,
            'latest_run_passed' => $latest?->status === SimulationRunStatus::Passed,
            'exact_agent_version' => $latest?->agent_version_id === $version->id,
            'exact_contract' => $latest?->contract_version_id === $contractVersion?->id,
            'fingerprint_current' => (bool) $fingerprintCurrent,
            'all_cases_passed' => $latest !== null && $latest->cases->count() === $enabledCount && $latest->cases->every(fn ($case) => $case->status === SimulationRunStatus::Passed),
            'no_runtime_policy_errors' => $latest !== null && ($latest->summary['runtime_policy_errors'] ?? 1) === 0,
            'registry_current' => $latest !== null && ($latest->summary['registry_keys'] ?? null) === $registryKeys,
        ];
        return new ReadinessReport(! in_array(false, $checks, true), $checks, $latest?->id);
    }

    public function registryKeys(array $allowed): array
    {
        $snapshot = [];
        foreach ($allowed as $key) {
            $definition = $this->actions->find($key);
            if (! $definition) continue;
            $snapshot[$key] = $this->hasher->hash(['input_schema'=>$definition->inputSchema,'output_schema'=>$definition->outputSchema,'effect'=>$definition->effect->value,'confirmation'=>$definition->confirmation->value,'required_entitlements'=>$definition->requiredEntitlements]);
        }
        ksort($snapshot);
        return $snapshot;
    }
}
