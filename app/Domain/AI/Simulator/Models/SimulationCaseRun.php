<?php

namespace App\Domain\AI\Simulator\Models;

use App\Domain\AI\Simulator\Enums\SimulationRunStatus;
use App\Domain\AI\Tenancy\AiTenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class SimulationCaseRun extends AiTenantModel
{
    protected $table = 'ai_simulation_case_runs';
    protected $guarded = ['*'];
    protected $casts = ['status' => SimulationRunStatus::class, 'scenario_snapshot' => 'encrypted:array', 'observations' => 'encrypted:array', 'transcript' => 'encrypted:array', 'action_traces' => 'encrypted:array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];

    protected static function booted(): void
    {
        parent::booted();
        static::creating(function (self $model): void {
            $model->uuid ??= (string) Str::uuid();
            $run = SimulationRun::query()->find($model->simulation_run_id);
            $scenario = SimulationScenario::query()->find($model->scenario_id);
            if (! $run || ! $scenario || $run->agent_id !== $scenario->agent_id) {
                throw new \DomainException('Simulation case aggregate is inconsistent.');
            }
        });
    }

    public function getRouteKeyName(): string { return 'uuid'; }
    public function run(): BelongsTo { return $this->belongsTo(SimulationRun::class, 'simulation_run_id'); }
    public function scenario(): BelongsTo { return $this->belongsTo(SimulationScenario::class); }
}
