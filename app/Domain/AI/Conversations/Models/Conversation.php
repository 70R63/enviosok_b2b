<?php

namespace App\Domain\AI\Conversations\Models;

use App\Domain\AI\Agents\Data\AuthorizedAiLifecycleActor;
use App\Domain\AI\Agents\Enums\AgentVersionStatus;
use App\Domain\AI\Agents\Models\Agent;
use App\Domain\AI\Agents\Models\AgentVersion;
use App\Domain\AI\Conversations\Enums\ConversationChannel;
use App\Domain\AI\Conversations\Enums\ConversationStatus;
use App\Domain\AI\Tenancy\AiTenantModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class Conversation extends AiTenantModel
{
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $table = 'ai_conversations';

    protected $guarded = ['*'];

    protected $casts = ['channel' => ConversationChannel::class, 'status' => ConversationStatus::class, 'turn_in_progress' => 'boolean', 'next_sequence' => 'integer', 'closed_at' => 'datetime'];

    protected function immutableIdentityAttributes(): array
    {
        return ['uuid', 'agent_id', 'agent_version_id', 'channel', 'created_by_user_id', 'status', 'turn_in_progress', 'next_sequence', 'closed_at', 'closed_by_user_id'];
    }

    protected static function booted(): void
    {
        parent::booted();
        self::creating(function (self $m) {
            $m->uuid ??= (string) Str::uuid();
            if ($m->channel !== ConversationChannel::InternalTest || $m->status !== ConversationStatus::Open || $m->turn_in_progress || $m->next_sequence !== 1) {
                throw new \DomainException('Invalid initial conversation state.');
            }$version = AgentVersion::query()->where('agent_id', $m->agent_id)->findOrFail($m->agent_version_id);
            if (! in_array($version->status, [AgentVersionStatus::Draft, AgentVersionStatus::Testing], true)) {
                throw new \DomainException('Internal conversations require a Draft or Testing version.');
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

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class)->orderBy('sequence');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function reserveTurn(AuthorizedAiLifecycleActor $a): array
    {
        $this->assertLifecycleActor($a);
        if ($this->originalAiStatus() === ConversationStatus::Closed->value) {
            throw new \DomainException('Closed conversations do not accept messages.');
        }if ($this->turn_in_progress) {
            throw new \DomainException('A conversation turn is already in progress.');
        }$user = $this->next_sequence;
        $assistant = $user + 1;
        $this->persistNamedLifecycle(['turn_in_progress', 'next_sequence'], function () use ($assistant) {
            $this->turn_in_progress = true;
            $this->next_sequence = $assistant + 1;
        });

        return [$user, $assistant];
    }

    public function finishTurn(AuthorizedAiLifecycleActor $a, bool $handoff): void
    {
        $this->assertLifecycleActor($a);
        if (! $this->turn_in_progress) {
            throw new \DomainException('No conversation turn is in progress.');
        }$this->persistNamedLifecycle(['turn_in_progress', 'status'], function () use ($handoff) {
            $this->turn_in_progress = false;
            if ($handoff) {
                $this->status = ConversationStatus::HandoffRequested;
            }
        });
    }

    public function close(AuthorizedAiLifecycleActor $a): void
    {
        $this->assertLifecycleActor($a);
        if ($this->turn_in_progress) {
            throw new \DomainException('A conversation turn is in progress.');
        }if ($this->originalAiStatus() === ConversationStatus::Closed->value) {
            throw new \DomainException('Conversation is already closed.');
        }$this->persistNamedLifecycle(['status', 'closed_at', 'closed_by_user_id'], function () use ($a) {
            $this->status = ConversationStatus::Closed;
            $this->closed_at = now();
            $this->closed_by_user_id = $a->actorUserId;
        });
    }
}
