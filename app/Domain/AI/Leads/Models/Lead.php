<?php

namespace App\Domain\AI\Leads\Models;

use App\Domain\AI\Agents\Models\Agent;
use App\Domain\AI\Agents\Models\AgentContractVersion;
use App\Domain\AI\Agents\Models\AgentVersion;
use App\Domain\AI\Conversations\Models\Conversation;
use App\Domain\AI\Conversations\Models\ConversationMessage;
use App\Domain\AI\Leads\Enums\LeadStatus;
use App\Domain\AI\Runtime\Models\RuntimeRun;
use App\Domain\AI\Tenancy\AiTenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class Lead extends AiTenantModel
{
    protected $table = 'ai_leads';

    protected $guarded = ['*'];

    protected $casts = ['status' => LeadStatus::class, 'data' => 'encrypted:array', 'detected_at' => 'datetime', 'verified_at' => 'datetime'];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isAiAppendOnly(): bool
    {
        return true;
    }

    protected function immutableIdentityAttributes(): array
    {
        return ['uuid', 'agent_id', 'agent_version_id', 'agent_contract_version_id', 'conversation_id', 'source_message_id', 'runtime_run_id', 'status', 'data', 'detected_at', 'verified_at'];
    }

    protected static function booted(): void
    {
        parent::booted();
        self::creating(function (self $lead): void {
            $lead->uuid ??= (string) Str::uuid();
            $conversation = Conversation::query()->findOrFail($lead->conversation_id);
            if ((int) $conversation->agent_id !== (int) $lead->agent_id || (int) $conversation->agent_version_id !== (int) $lead->agent_version_id) {
                throw new \DomainException('Lead conversation identity is inconsistent.');
            }
        });
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

    public function outcomes(): HasMany
    {
        return $this->hasMany(OutcomeEvent::class);
    }

    public function enrich(array $data, LeadStatus $status): void
    {
        if (DB::transactionLevel() < 1) {
            throw new \DomainException('Lead enrichment requires an active transaction.');
        }
        if ($this->status === LeadStatus::Verified && $status !== LeadStatus::Verified) {
            throw new \DomainException('A verified Lead cannot be downgraded.');
        }
        $this->persistNamedLifecycle(['data', 'status', 'verified_at'], function () use ($data, $status): void {
            $this->data = $data;
            $this->status = $status;
            $this->verified_at = $status === LeadStatus::Verified ? ($this->verified_at ?? now()) : null;
        });
    }
}
