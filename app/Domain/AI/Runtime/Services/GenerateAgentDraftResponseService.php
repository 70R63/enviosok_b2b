<?php

namespace App\Domain\AI\Runtime\Services;

use App\Domain\AI\Agents\Enums\AgentVersionStatus;
use App\Domain\AI\Agents\Models\Agent;
use App\Domain\AI\Agents\Models\AgentVersion;
use App\Domain\AI\Agents\Services\AiLifecycleAuthorization;
use App\Domain\AI\Knowledge\Contracts\KnowledgeRetriever;
use App\Domain\AI\Knowledge\Data\KnowledgeSearchMatch;
use App\Domain\AI\Knowledge\Data\KnowledgeSearchQuery;
use App\Domain\AI\Providers\ProviderRegistry;
use App\Domain\AI\Runtime\Data\AgentRuntimeResponseData;
use App\Domain\AI\Runtime\Data\ModelRequestData;
use App\Domain\AI\Runtime\Data\RuntimeQuestionData;
use App\Domain\AI\Runtime\Enums\RuntimeRunStatus;
use App\Domain\AI\Runtime\Enums\AiExecutionMode;
use App\Domain\AI\Runtime\Exceptions\InvalidModelResponseException;
use App\Domain\AI\Runtime\Exceptions\ModelProviderAuthenticationException;
use App\Domain\AI\Runtime\Exceptions\ModelProviderException;
use App\Domain\AI\Runtime\Exceptions\ModelProviderNotConfiguredException;
use App\Domain\AI\Runtime\Exceptions\ModelProviderRateLimitException;
use App\Domain\AI\Runtime\Exceptions\ModelProviderTimeoutException;
use App\Domain\AI\Runtime\Exceptions\ModelProviderUnavailableException;
use App\Domain\AI\Runtime\Models\RuntimeRun;
use App\Domain\AI\Runtime\Policy\AgentRuntimePolicyCompiler;
use App\Domain\AI\Runtime\Support\RuntimeOutputSchema;
use App\Domain\AI\Tenancy\AiTenantBoundary;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Domain\AI\Actions\ActionRegistry;
use App\Domain\AI\Runtime\Support\ActionRuntimeOutputSchema;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\AI\Usage\MeteredRuntimeRunService;
use App\Domain\AI\Cost\AiCostGuard;

final class GenerateAgentDraftResponseService
{
    public function __construct(private AiLifecycleAuthorization $auth, private AiTenantBoundary $tenants, private KnowledgeRetriever $retriever, private ProviderRegistry $providers, private AgentRuntimePolicyCompiler $policy, private ActionRegistry $actions, private MeteredRuntimeRunService $runs, private ?AiCostGuard $costGuard = null) {}

    public function generate(User $actor, Agent $agent, RuntimeQuestionData $q, ?AgentVersion $pinnedVersion = null, array $history = [], AiExecutionMode $mode = AiExecutionMode::Live, ?string $conversationTurnTokenHash = null): AgentRuntimeResponseData
    {
        $authorized = $this->auth->authorize($actor);
        $this->tenants->assertResourceBelongsToCurrentTenant($agent);
        [$agent,$version] = DB::transaction(function () use ($agent, $pinnedVersion, $mode) {
            $a = Agent::query()->lockForUpdate()->with('contract')->findOrFail($agent->id);
            $statuses = [AgentVersionStatus::Draft->value, AgentVersionStatus::Testing->value, AgentVersionStatus::Published->value, AgentVersionStatus::Retired->value];
            if ($mode === AiExecutionMode::Simulation) $statuses[] = AgentVersionStatus::Approved->value;
            $query = AgentVersion::query()->where('agent_id', $a->id)->whereIn('status', $statuses);
            $v = $pinnedVersion ? $query->whereKey($pinnedVersion->id)->lockForUpdate()->firstOrFail() : $query->orderByDesc('version_number')->lockForUpdate()->firstOrFail();
            if (! $a->contract || ! $v->agent_contract_version_id) {
                throw new \DomainException('A complete draft aggregate is required.');
            }$v->load('contractVersion');
            if (! $v->contractVersion || $v->contractVersion->agent_contract_id !== $a->contract->id) {
                throw new \DomainException('The runtime aggregate is inconsistent.');
            }

return [$a, $v];
        });
        $matches = $this->limit($this->retriever->retrieve($version, KnowledgeSearchQuery::from($q->value, 6)));
        $run = $this->start($authorized, $agent, $version, $mode, $conversationTurnTokenHash);
        if ($matches === []) {
            DB::transaction(fn () => $run->fresh()->skip($this->auth->authorize($actor), $conversationTurnTokenHash));

            return new AgentRuntimeResponseData('No encontramos información suficiente para responder.', [], 'low', true, 'insufficient_knowledge', [], $run->id);
        }$ids = [];
        $knowledge = [];
        foreach ($matches as $i => $m) {
            $id = 'K'.($i + 1);
            $ids[] = $id;
            $knowledge[] = ['citation_id' => $id, 'source_name' => $m->sourceName, 'version' => $m->versionNumber, 'locator' => $m->locator, 'excerpt' => $m->excerpt, 'knowledge_chunk_id' => $m->chunkId];
        }$input = ['question' => $q->value, 'knowledge' => array_map(fn ($k) => array_diff_key($k, ['knowledge_chunk_id' => true]), $knowledge)];
        if ($history !== []) {
            $input['history'] = $this->history($history);
        }$tenant=Tenant::query()->findOrFail($authorized->tenantId);$allowedActions=array_values(array_intersect($version->contractVersion->allowed_actions??[],array_keys($this->actions->availableFor($tenant))));$definitions=array_map(fn($key)=>$this->actions->find($key),$allowedActions);
        $schema=$definitions===[]?RuntimeOutputSchema::schema($ids):ActionRuntimeOutputSchema::schema($ids,$definitions);
        $purpose=$mode===AiExecutionMode::Live&&in_array($version->status,[AgentVersionStatus::Published,AgentVersionStatus::Retired],true)?'public_webchat':'agent_draft_simulation';
        $request = new ModelRequestData($this->policy->compile($agent, $version), $input, $schema, 600, 'none', $this->safety($authorized->tenantId, $authorized->actorUserId, $purpose), $purpose);
        try {
            $model = $this->providers->model()->generate($request);
            $valid = AgentRuntimeResponseData::validate($model->structuredOutput, $ids);
            $sources = [];
            foreach ($valid->citationIds as $id) {
                $sources[] = $knowledge[((int) substr($id, 1)) - 1];
            }$result = new AgentRuntimeResponseData($valid->answer, $valid->citationIds, $valid->confidence, $valid->needsHandoff, $valid->handoffReason, $sources, $run->id, $valid->leadCandidate, $valid->resolvedCandidate, $valid->actionRequest);
            $pricing = (config('ai.providers.openai.pricing') ?? [])[$model->model] ?? null;
            if ($this->costGuard && Schema::hasTable('ai_cost_ledger')) {
                $ledger = $this->costGuard->record($tenant, $run, ['input_tokens'=>$model->inputUnits,'cached_input_tokens'=>$model->cachedInputUnits,'output_tokens'=>$model->outputUnits,'total_tokens'=>$model->totalUnits]);
                $snapshot = $ledger->rate_snapshot ?? null;
                if (is_array($snapshot)) $pricing = $snapshot;
            }
            if (! is_array($pricing)) throw new InvalidModelResponseException('Pricing snapshot is not configured.');
            DB::transaction(fn () => $run->fresh()->complete($this->auth->authorize($actor), $model, $result, $pricing, $conversationTurnTokenHash));

            return $result;
        } catch (\Throwable$e) {
            $code = match (true) {
                $e instanceof ModelProviderNotConfiguredException => 'provider_not_configured',$e instanceof ModelProviderAuthenticationException => 'provider_authentication',$e instanceof ModelProviderRateLimitException => 'provider_rate_limit',$e instanceof ModelProviderTimeoutException => 'provider_timeout',$e instanceof ModelProviderUnavailableException => 'provider_unavailable',$e instanceof InvalidModelResponseException => 'invalid_model_response',default => 'runtime_failed'
            };
            $this->costGuard?->release($run);
            DB::transaction(fn () => $run->fresh()->fail($this->auth->authorize($actor), $code, $e instanceof ModelProviderException ? $e->failure : null, $conversationTurnTokenHash));
            throw $e;
        }
    }

    private function history(array $history): array
    {
        $allowed = ['user', 'assistant'];
        $out = [];
        $chars = 0;
        $maxMessages = min(6, max(1, (int) config('ai.conversation_history_max_messages', 6)));
        $maxChars = min(6000, max(1, (int) config('ai.conversation_history_max_characters', 6000)));
        foreach (array_slice($history, -$maxMessages) as $item) {
            if (! is_array($item) || ! in_array($item['role'] ?? null, $allowed, true) || ! is_string($item['content'] ?? null)) {
                continue;
            }$content = mb_substr($item['content'], 0, $maxChars - $chars);
            if ($content === '') {
                break;
            }$out[] = ['role' => $item['role'], 'content' => $content];
            $chars += mb_strlen($content);
            if ($chars >= $maxChars) {
                break;
            }
        }

return $out;
    }

    private function start($actor, Agent $a, AgentVersion $v, AiExecutionMode $mode, ?string $conversationTurnTokenHash): RuntimeRun
    {
        $tenant=Tenant::query()->findOrFail($actor->tenantId);return $this->runs->start($tenant,$mode,function () use ($actor, $a, $v, $mode, $conversationTurnTokenHash) {
            $r = new RuntimeRun;
            $r->agent_id = $a->id;
            $r->agent_version_id = $v->id;
            if (Schema::hasColumn('ai_runtime_runs', 'conversation_turn_token_hash')) $r->conversation_turn_token_hash = $conversationTurnTokenHash;
            $r->purpose = $mode===AiExecutionMode::Live&&in_array($v->status,[AgentVersionStatus::Published,AgentVersionStatus::Retired],true)?'public_webchat':'agent_draft_simulation';
            if (Schema::hasColumn('ai_runtime_runs', 'execution_mode')) {
                $r->execution_mode = $mode->value;
            }
            $r->status = RuntimeRunStatus::Started;
            $r->provider_code = (string) config('ai.default_provider');
            $r->model_code = (string) config('ai.providers.openai.model');
            $r->input_units = $r->cached_input_units = $r->output_units = $r->total_units = $r->estimated_cost_microusd = 0;
            $r->input_price_microusd_per_million = $r->cached_input_price_microusd_per_million = $r->output_price_microusd_per_million = 0;
            $r->citation_count = 0;
            $r->needs_handoff = false;
            $r->created_by_user_id = $actor->actorUserId;
            $r->started_at = now();
            $r->save();

            return $r;
        });
    }

    private function limit(array $matches): array
    {
        $out = [];
        $chars = 0;
        foreach (array_slice($matches, 0, 6) as $m) {
            $remaining = 12000 - $chars;
            if ($remaining <= 0) {
                break;
            }$excerpt = mb_substr($m->excerpt, 0, min(1200, $remaining));
            if ($excerpt === '') {
                continue;
            }$out[] = new KnowledgeSearchMatch($m->sourceUuid, $m->sourceName, $m->versionNumber, $m->sourceType, $m->locator, $excerpt, $m->score, $m->sequence, $m->chunkId);
            $chars += mb_strlen($excerpt);
        }

return $out;
    }

    private function safety(int $tenant,int $user,string $purpose='agent_draft_simulation'): string
    {
        $key = (string) config('app.key');
        if ($key === '') {
            throw new ModelProviderNotConfiguredException('Application safety material is not configured.');
        }

return hash_hmac('sha256',$tenant.':'.$user.':'.$purpose,$key);
    }
}
