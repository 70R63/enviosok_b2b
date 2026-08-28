<?php

namespace App\Domain\AI\Runtime\Models;

use App\Domain\AI\Agents\Data\AuthorizedAiLifecycleActor;
use App\Domain\AI\Runtime\Data\{AgentRuntimeResponseData, ModelResponseData, ProviderFailureData};
use App\Domain\AI\Runtime\Enums\{AiExecutionMode, RuntimeRunStatus};
use App\Domain\AI\Tenancy\AiTenantModel;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

final class RuntimeRun extends AiTenantModel
{
    protected $table = 'ai_runtime_runs';
    protected $guarded = ['*'];
    protected $casts = ['execution_mode'=>AiExecutionMode::class,'status'=>RuntimeRunStatus::class,'needs_handoff'=>'boolean','started_at'=>'datetime','completed_at'=>'datetime','failed_at'=>'datetime'];

    protected function immutableIdentityAttributes(): array
    {
        return ['uuid','agent_id','agent_version_id','conversation_turn_token_hash','purpose','execution_mode','status','provider_code','model_code','provider_http_status','provider_error_type','provider_error_code','provider_error_param','provider_request_id','client_request_id','provider_response_id','input_units','cached_input_units','output_units','total_units','estimated_cost_microusd','input_price_microusd_per_million','cached_input_price_microusd_per_million','output_price_microusd_per_million','citation_count','confidence','needs_handoff','handoff_reason','latency_ms','safe_error_code','created_by_user_id','started_at','completed_at','failed_at'];
    }

    protected static function booted(): void
    {
        parent::booted();
        static::creating(function (self $model): void {
            if ($model->status !== RuntimeRunStatus::Started) throw new \DomainException('Runtime runs must start in started status.');
            $model->uuid ??= (string) Str::uuid();
            if (Schema::hasColumn($model->getTable(), 'execution_mode')) $model->execution_mode ??= AiExecutionMode::Live;
        });
    }

    public function isAiAppendOnly(): bool { return true; }

    public function complete(AuthorizedAiLifecycleActor $actor, ModelResponseData $model, AgentRuntimeResponseData $response, array $pricing, ?string $turnTokenHash = null): void
    {
        $this->ensureStarted(); $this->assertLifecycleActor($actor); $this->assertTurnOwnership($turnTokenHash); $cost=self::cost($model,$pricing);
        $this->persistNamedLifecycle($this->mutable(), function () use ($model,$response,$pricing,$cost): void {
            $this->status=RuntimeRunStatus::Completed;$this->model_code=$model->model;$this->provider_response_id=$model->providerResponseId;$this->input_units=$model->inputUnits;$this->cached_input_units=$model->cachedInputUnits;$this->output_units=$model->outputUnits;$this->total_units=$model->totalUnits;$this->estimated_cost_microusd=$cost;$this->input_price_microusd_per_million=$pricing['input'];$this->cached_input_price_microusd_per_million=$pricing['cached_input'];$this->output_price_microusd_per_million=$pricing['output'];$this->citation_count=count($response->citationIds);$this->confidence=$response->confidence;$this->needs_handoff=$response->needsHandoff;$this->handoff_reason=$response->handoffReason;$this->latency_ms=$model->latencyMilliseconds;$this->completed_at=now();
        });
    }

    public function fail(AuthorizedAiLifecycleActor $actor, string $code, ?ProviderFailureData $failure=null, ?string $turnTokenHash=null): void
    {
        $this->ensureStarted();$this->assertLifecycleActor($actor);$this->assertTurnOwnership($turnTokenHash);
        if(!in_array($code,['provider_not_configured','provider_authentication','provider_rate_limit','provider_unavailable','provider_timeout','invalid_model_response','runtime_failed'],true))throw new \InvalidArgumentException('Invalid runtime error code.');
        $this->persistNamedLifecycle($this->mutable(),function()use($code,$failure):void{$this->status=RuntimeRunStatus::Failed;$this->safe_error_code=$code;if($failure){$this->provider_http_status=$failure->httpStatus;$this->provider_error_type=$failure->errorType;$this->provider_error_code=$failure->errorCode;$this->provider_error_param=$failure->errorParam;$this->provider_request_id=$failure->providerRequestId;$this->client_request_id=$failure->clientRequestId;}$this->failed_at=now();});
    }

    public function skip(AuthorizedAiLifecycleActor $actor, ?string $turnTokenHash=null): void
    {
        $this->ensureStarted();$this->assertLifecycleActor($actor);$this->assertTurnOwnership($turnTokenHash);$this->persistNamedLifecycle($this->mutable(),function():void{$this->status=RuntimeRunStatus::SkippedNoKnowledge;$this->confidence='low';$this->needs_handoff=true;$this->handoff_reason='insufficient_knowledge';$this->completed_at=now();});
    }

    private function assertTurnOwnership(?string $turnTokenHash): void
    {
        if (! Schema::hasColumn($this->getTable(), 'conversation_turn_token_hash')) return;
        $stored = $this->conversation_turn_token_hash;
        if ($stored === null) return; // Runs unrelated to conversations remain mutable.
        if (! is_string($turnTokenHash) || ! preg_match('/^[a-f0-9]{64}$/D', $turnTokenHash) || ! hash_equals($stored, $turnTokenHash)) {
            throw new \App\Domain\AI\Conversations\Exceptions\ConversationTurnSupersededException;
        }
    }

    private function ensureStarted(): void { if($this->originalAiStatus()!==RuntimeRunStatus::Started->value)throw new \DomainException('Runtime run is terminal.'); }
    private function mutable(): array { return array_values(array_diff($this->immutableIdentityAttributes(),['uuid','agent_id','agent_version_id','conversation_turn_token_hash','purpose','execution_mode','provider_code','model_code','created_by_user_id','started_at'])); }
    private static function cost(ModelResponseData$model,array$pricing):int{$uncached=max(0,$model->inputUnits-$model->cachedInputUnits);return intdiv($uncached*$pricing['input']+$model->cachedInputUnits*$pricing['cached_input']+$model->outputUnits*$pricing['output']+999999,1000000);}
}
