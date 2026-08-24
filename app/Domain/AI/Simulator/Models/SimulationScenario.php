<?php

namespace App\Domain\AI\Simulator\Models;

use App\Domain\AI\Agents\Models\Agent;
use App\Domain\AI\Simulator\Support\SimulationScenarioDefinition;
use App\Domain\AI\Support\Exceptions\AiTenantMismatchException;
use App\Domain\AI\Tenancy\AiTenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class SimulationScenario extends AiTenantModel
{
    protected $table = 'ai_simulation_scenarios';
    protected $guarded = ['*'];
    protected $casts = ['enabled' => 'boolean', 'definition' => 'encrypted:array'];

    protected static function booted(): void
    {
        parent::booted();
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
        static::saving(function (self $model): void {
            if (! Agent::query()->whereKey($model->agent_id)->exists()) {
                throw new AiTenantMismatchException('Scenario agent must belong to the active tenant.');
            }
            $model->definition = app(SimulationScenarioDefinition::class)->normalize($model->definition);
        });
    }

    public function getRouteKeyName(): string { return 'uuid'; }
    public function agent(): BelongsTo { return $this->belongsTo(Agent::class); }
}
