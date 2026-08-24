<?php

namespace App\Domain\AI\Simulator\Models;

use App\Domain\AI\Agents\Models\{Agent, AgentContractVersion, AgentVersion};
use App\Domain\AI\Simulator\Enums\SimulationRunStatus;
use App\Domain\AI\Tenancy\AiTenantModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Str;

final class SimulationRun extends AiTenantModel
{
    protected $table = 'ai_simulation_runs';
    protected $guarded = ['*'];
    protected $casts = ['status' => SimulationRunStatus::class, 'summary' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];

    protected static function booted(): void
    {
        parent::booted();
        static::creating(function (self $model): void {
            $model->uuid ??= (string) Str::uuid();
            $agent = Agent::query()->find($model->agent_id);
            $version = AgentVersion::query()->find($model->agent_version_id);
            $contract = AgentContractVersion::query()->with('contract')->find($model->contract_version_id);
            if (! $agent || ! $version || ! $contract || $version->agent_id !== $agent->id || $version->agent_contract_version_id !== $contract->id || $contract->contract?->agent_id !== $agent->id) {
                throw new \DomainException('Simulation run aggregate is inconsistent.');
            }
        });
    }

    public function getRouteKeyName(): string { return 'uuid'; }
    public function agent(): BelongsTo { return $this->belongsTo(Agent::class); }
    public function agentVersion(): BelongsTo { return $this->belongsTo(AgentVersion::class); }
    public function contractVersion(): BelongsTo { return $this->belongsTo(AgentContractVersion::class, 'contract_version_id'); }
    public function cases(): HasMany { return $this->hasMany(SimulationCaseRun::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by_user_id'); }
}
