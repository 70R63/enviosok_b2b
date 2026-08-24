<?php

namespace App\Domain\AI\Simulator\Services;

use App\Domain\AI\Agents\Models\{Agent, AgentContract, AgentContractVersion, AgentVersion};
use App\Domain\AI\Agents\Services\{AiLifecycleAuthorization, AgentVersionLifecycleService};
use App\Domain\Network\Billing\EntitlementService;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class PublishReadyAgentVersionService
{
    public function __construct(private AiLifecycleAuthorization $authorization, private AiReadinessService $readiness, private AgentVersionLifecycleService $lifecycle, private EntitlementService $entitlements) {}

    public function publish(User $actor, Agent $inputAgent, AgentVersion $inputVersion): AgentVersion
    {
        $authorized = $this->authorization->authorize($actor);
        return DB::transaction(function () use ($authorized, $actor, $inputAgent, $inputVersion): AgentVersion {
            $this->authorization->revalidate($authorized);
            $agent = Agent::query()->lockForUpdate()->findOrFail($inputAgent->id);
            $contract = AgentContract::query()->where('agent_id', $agent->id)->lockForUpdate()->firstOrFail();
            $contractVersion = AgentContractVersion::query()->where('agent_contract_id', $contract->id)->lockForUpdate()->findOrFail($inputVersion->agent_contract_version_id);
            $version = AgentVersion::query()->where('agent_id', $agent->id)->where('agent_contract_version_id', $contractVersion->id)->lockForUpdate()->findOrFail($inputVersion->id);
            $tenant = Tenant::query()->findOrFail($agent->tenant_id);
            $this->entitlements->lockCurrentAuthority($tenant);
            $agent->setRelation('contract', $contract);
            $version->setRelation('contractVersion', $contractVersion->setRelation('contract', $contract));
            if (! $this->readiness->evaluateLocked($agent, $version)->ready) {
                throw new \DomainException('Agent version is not ready to publish.');
            }
            return $this->lifecycle->publish($actor, $version);
        }, 3);
    }
}
