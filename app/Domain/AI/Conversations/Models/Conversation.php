<?php

namespace App\Domain\AI\Conversations\Models;

use App\Domain\AI\Agents\Data\AuthorizedAiLifecycleActor;
use App\Domain\AI\Agents\Enums\AgentVersionStatus;
use App\Domain\AI\Agents\Models\Agent;
use App\Domain\AI\Agents\Models\AgentVersion;
use App\Domain\AI\Conversations\Enums\ConversationChannel;
use App\Domain\AI\Conversations\Enums\ConversationMessageRole;
use App\Domain\AI\Conversations\Enums\ConversationMessageStatus;
use App\Domain\AI\Conversations\Enums\ConversationStatus;
use App\Domain\AI\Conversations\Exceptions\ConversationTurnBusyException;
use App\Domain\AI\Conversations\Exceptions\ConversationTurnSupersededException;
use App\Domain\AI\Handoff\Models\HumanHandoff;
use App\Domain\AI\Leads\Models\Lead;
use App\Domain\AI\Leads\Models\OutcomeEvent;
use App\Domain\AI\Actions\Models\ActionRun;
use App\Domain\AI\Tenancy\AiTenantModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

final class Conversation extends AiTenantModel
{
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $table = 'ai_conversations';

    protected $guarded = ['*'];

    protected $casts = ['channel' => ConversationChannel::class, 'status' => ConversationStatus::class, 'turn_in_progress' => 'boolean', 'next_sequence' => 'integer', 'active_turn_started_at' => 'datetime', 'active_turn_heartbeat_at' => 'datetime', 'active_turn_expires_at' => 'datetime', 'closed_at' => 'datetime'];

    protected function immutableIdentityAttributes(): array
    {
        return ['uuid', 'agent_id', 'agent_version_id', 'channel', 'created_by_user_id', 'status', 'turn_in_progress', 'active_turn_token_hash', 'active_turn_assistant_message_id', 'active_turn_started_at', 'active_turn_heartbeat_at', 'active_turn_expires_at', 'active_handoff_id', 'next_sequence', 'closed_at', 'closed_by_user_id'];
    }

    protected static function booted(): void
    {
        parent::booted();
        self::creating(function (self $m) {
            $m->uuid ??= (string) Str::uuid();
            if (! in_array($m->channel, [ConversationChannel::InternalTest, ConversationChannel::Webchat, ConversationChannel::WhatsApp], true) || $m->status !== ConversationStatus::Open || $m->turn_in_progress || $m->next_sequence !== 1) {
                throw new \DomainException('Invalid initial conversation state.');
            }$version = AgentVersion::query()->where('agent_id', $m->agent_id)->findOrFail($m->agent_version_id);
            $allowed = in_array($m->channel, [ConversationChannel::Webchat, ConversationChannel::WhatsApp], true)
                ? [AgentVersionStatus::Published, AgentVersionStatus::Retired]
                : [AgentVersionStatus::Draft, AgentVersionStatus::Testing];
            if (! in_array($version->status, $allowed, true)) {
                throw new \DomainException('Conversation version is not executable for its channel.');
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

    public function lead(): HasOne
    {
        return $this->hasOne(Lead::class);
    }

    public function outcomes(): HasMany
    {
        return $this->hasMany(OutcomeEvent::class);
    }

    public function handoffs(): HasMany
    {
        return $this->hasMany(HumanHandoff::class);
    }

    public function actionRuns(): HasMany
    {
        return $this->hasMany(ActionRun::class);
    }

    public function activeHandoff(): BelongsTo
    {
        return $this->belongsTo(HumanHandoff::class, 'active_handoff_id');
    }

    public function reserveTurn(AuthorizedAiLifecycleActor $a, string $tokenHash, \DateTimeInterface $startedAt, \DateTimeInterface $expiresAt): array
    {
        $this->assertLifecycleActor($a);
        if ($this->originalAiStatus() !== ConversationStatus::Open->value) {
            throw new \DomainException('Only an open AI conversation accepts AI turns.');
        }if ($this->turn_in_progress || $this->active_turn_token_hash !== null) {
            throw new ConversationTurnBusyException('A conversation turn is already in progress.');
        }if (! preg_match('/^[a-f0-9]{64}$/D', $tokenHash) || $expiresAt <= $startedAt) {
            throw new \LogicException('Invalid conversation turn ownership.');
        }$user = $this->next_sequence;
        $assistant = $user + 1;
        $this->persistNamedLifecycle(['turn_in_progress', 'active_turn_token_hash', 'active_turn_started_at', 'active_turn_heartbeat_at', 'active_turn_expires_at', 'next_sequence'], function () use ($assistant, $tokenHash, $startedAt, $expiresAt) {
            $this->turn_in_progress = true;
            $this->active_turn_token_hash = $tokenHash;
            $this->active_turn_started_at = $startedAt;
            $this->active_turn_heartbeat_at = $startedAt;
            $this->active_turn_expires_at = $expiresAt;
            $this->next_sequence = $assistant + 1;
        });

        return [$user, $assistant];
    }

    public function bindActiveTurnAssistant(AuthorizedAiLifecycleActor $a, ConversationMessage $assistant, string $tokenHash): void
    {
        $this->assertLifecycleActor($a);
        $this->assertActiveTurn($tokenHash);
        if ($assistant->conversation_id !== $this->id || $assistant->role !== ConversationMessageRole::Assistant || $assistant->status !== ConversationMessageStatus::Pending || ! hash_equals((string) $assistant->turn_token_hash, $tokenHash)) {
            throw new \LogicException('Invalid active turn assistant.');
        }
        $this->persistNamedLifecycle(['active_turn_assistant_message_id'], fn () => $this->active_turn_assistant_message_id = $assistant->id);
    }

    public function heartbeatTurn(AuthorizedAiLifecycleActor $a, string $tokenHash, \DateTimeInterface $expiresAt): void
    {
        $this->assertLifecycleActor($a);
        $this->assertActiveTurn($tokenHash);
        $this->persistNamedLifecycle(['active_turn_heartbeat_at', 'active_turn_expires_at'], function () use ($expiresAt): void {
            $this->active_turn_heartbeat_at = now();
            $this->active_turn_expires_at = $expiresAt;
        });
    }

    public function assertActiveTurn(string $tokenHash, ?int $assistantId = null): void
    {
        if (! $this->turn_in_progress || ! is_string($this->active_turn_token_hash) || ! hash_equals($this->active_turn_token_hash, $tokenHash) || ($assistantId !== null && (int) $this->active_turn_assistant_message_id !== $assistantId)) {
            throw new ConversationTurnSupersededException;
        }
    }

    public function requestHuman(AuthorizedAiLifecycleActor $a, int $handoffId): void
    {
        $this->assertLifecycleActor($a);
        if ($this->status !== ConversationStatus::HandoffRequested || $this->active_handoff_id !== null) {
            throw new \DomainException('Conversation cannot request another active handoff.');
        } $this->persistNamedLifecycle(['active_handoff_id'], fn () => $this->active_handoff_id = $handoffId);
    }

    public function activateHuman(AuthorizedAiLifecycleActor $a): void
    {
        $this->assertLifecycleActor($a);
        if ($this->status !== ConversationStatus::HandoffRequested || ! $this->active_handoff_id) {
            throw new \DomainException('Conversation has no requested handoff.');
        } $this->persistNamedLifecycle(['status'], fn () => $this->status = ConversationStatus::HumanActive);
    }

    public function releaseHuman(AuthorizedAiLifecycleActor $a): void
    {
        $this->assertLifecycleActor($a);
        if ($this->status !== ConversationStatus::HumanActive || ! $this->active_handoff_id) {
            throw new \DomainException('Conversation is not under human control.');
        } $this->persistNamedLifecycle(['status', 'active_handoff_id'], function () {
            $this->status = ConversationStatus::Open;
            $this->active_handoff_id = null;
        });
    }

    public function reserveHumanMessage(AuthorizedAiLifecycleActor $a): int
    {
        $this->assertLifecycleActor($a);
        if ($this->status !== ConversationStatus::HumanActive || $this->turn_in_progress) {
            throw new \DomainException('Conversation is not available for a human message.');
        } $sequence = $this->next_sequence;
        $this->persistNamedLifecycle(['next_sequence'], fn () => $this->next_sequence = $sequence + 1);

        return $sequence;
    }

    public function reserveVisitorMessageDuringHumanHandoff(AuthorizedAiLifecycleActor $a): int
    {
        $this->assertLifecycleActor($a);
        if ($this->status !== ConversationStatus::HumanActive || $this->turn_in_progress) {
            throw new \DomainException('Conversation is not accepting a visitor message during human handoff.');
        }
        $sequence = $this->next_sequence;
        $this->persistNamedLifecycle(['next_sequence'], fn () => $this->next_sequence = $sequence + 1);

        return $sequence;
    }

    public function reserveActionCompletion(AuthorizedAiLifecycleActor $a, string $tokenHash, \DateTimeInterface $startedAt, \DateTimeInterface $expiresAt): int
    {
        $this->assertLifecycleActor($a);
        if ($this->status !== ConversationStatus::Open || $this->turn_in_progress || $this->active_turn_token_hash !== null || !preg_match('/^[a-f0-9]{64}$/D',$tokenHash) || $expiresAt <= $startedAt) throw new \DomainException('Conversation is not available for Action completion.');
        $sequence=$this->next_sequence;
        $this->persistNamedLifecycle(['turn_in_progress','active_turn_token_hash','active_turn_started_at','active_turn_heartbeat_at','active_turn_expires_at','next_sequence'],function()use($sequence,$tokenHash,$startedAt,$expiresAt){$this->turn_in_progress=true;$this->active_turn_token_hash=$tokenHash;$this->active_turn_started_at=$startedAt;$this->active_turn_heartbeat_at=$startedAt;$this->active_turn_expires_at=$expiresAt;$this->next_sequence=$sequence+1;});
        return $sequence;
    }

    public function finishTurn(AuthorizedAiLifecycleActor $a, bool $handoff, string $tokenHash, ?int $assistantId = null): void
    {
        $this->assertLifecycleActor($a);
        if (! $this->turn_in_progress) {
            throw new \DomainException('No conversation turn is in progress.');
        }
        $this->assertActiveTurn($tokenHash, $assistantId);
        $this->persistNamedLifecycle(['turn_in_progress', 'active_turn_token_hash', 'active_turn_assistant_message_id', 'active_turn_started_at', 'active_turn_heartbeat_at', 'active_turn_expires_at', 'status'], function () use ($handoff) {
            $this->turn_in_progress = false;
            $this->active_turn_token_hash = null;
            $this->active_turn_assistant_message_id = null;
            $this->active_turn_started_at = null;
            $this->active_turn_heartbeat_at = null;
            $this->active_turn_expires_at = null;
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
        }$this->persistNamedLifecycle(['status', 'active_handoff_id', 'closed_at', 'closed_by_user_id'], function () use ($a) {
            $this->status = ConversationStatus::Closed;
            $this->active_handoff_id = null;
            $this->closed_at = now();
            $this->closed_by_user_id = $a->actorUserId;
        });
    }
}
