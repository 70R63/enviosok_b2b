<?php

namespace App\Domain\AI\Leads\Models;

use App\Domain\AI\Agents\Models\Agent;
use App\Domain\AI\Agents\Models\AgentContractVersion;
use App\Domain\AI\Agents\Models\AgentVersion;
use App\Domain\AI\Conversations\Models\Conversation;
use App\Domain\AI\Conversations\Models\ConversationMessage;
use App\Domain\AI\Leads\Enums\OutcomeStatus;
use App\Domain\AI\Leads\Enums\OutcomeType;
use App\Domain\AI\Runtime\Models\RuntimeRun;
use App\Domain\AI\Tenancy\AiTenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class OutcomeEvent extends AiTenantModel
{
    protected $table = 'ai_outcome_events';

    protected $guarded = ['*'];

    protected $casts = ['outcome_type' => OutcomeType::class, 'status' => OutcomeStatus::class, 'evidence' => 'array', 'detected_at' => 'datetime', 'verified_at' => 'datetime'];

    public function isAiAppendOnly(): bool
    {
        return true;
    }

    protected function immutableIdentityAttributes(): array
    {
        return ['uuid', 'agent_id', 'agent_version_id', 'agent_contract_version_id', 'conversation_id', 'source_message_id', 'runtime_run_id', 'lead_id', 'outcome_type', 'status', 'evidence', 'detected_at', 'verified_at'];
    }

    protected static function booted(): void
    {
        parent::booted();
        self::creating(fn (self $event) => $event->uuid ??= (string) Str::uuid());
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function agentVersion(): BelongsTo
    {
        return $this->belongsTo(AgentVersion::class);
    }

    public function contractVersion(): BelongsTo
    {
        return $this->belongsTo(AgentContractVersion::class, 'agent_contract_version_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sourceMessage(): BelongsTo
    {
        return $this->belongsTo(ConversationMessage::class, 'source_message_id');
    }

    public function runtimeRun(): BelongsTo
    {
        return $this->belongsTo(RuntimeRun::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
