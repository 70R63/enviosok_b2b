<?php

namespace App\Domain\AI\Conversations\Models;

use App\Domain\AI\Agents\Data\AuthorizedAiLifecycleActor;
use App\Domain\AI\Agents\Enums\AgentVersionStatus;
use App\Domain\AI\Agents\Models\Agent;
use App\Domain\AI\Agents\Models\AgentVersion;
use App\Domain\AI\Conversations\Enums\ConversationChannel;
use App\Domain\AI\Conversations\Enums\ConversationStatus;
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

    protected $casts = ['channel' => ConversationChannel::class, 'status' => ConversationStatus::class, 'turn_in_progress' => 'boolean', 'next_sequence' => 'integer', 'closed_at' => 'datetime'];

    protected function immutableIdentityAttributes(): array
    {
        return ['uuid', 'agent_id', 'agent_version_id', 'channel', 'created_by_user_id', 'status', 'turn_in_progress', 'active_handoff_id', 'next_sequence', 'closed_at', 'closed_by_user_id'];
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

    public function reserveTurn(AuthorizedAiLifecycleActor $a): array
    {
        $this->assertLifecycleActor($a);
        if ($this->originalAiStatus() !== ConversationStatus::Open->value) {
            throw new \DomainException('Only an open AI conversation accepts AI turns.');
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

    public function reserveActionCompletion(AuthorizedAiLifecycleActor $a): int
    {
        $this->assertLifecycleActor($a);
        if ($this->status !== ConversationStatus::Open || $this->turn_in_progress) throw new \DomainException('Conversation is not available for Action completion.');
        $sequence=$this->next_sequence;
        $this->persistNamedLifecycle(['turn_in_progress','next_sequence'],function()use($sequence){$this->turn_in_progress=true;$this->next_sequence=$sequence+1;});
        return $sequence;
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
        }$this->persistNamedLifecycle(['status', 'active_handoff_id', 'closed_at', 'closed_by_user_id'], function () use ($a) {
            $this->status = ConversationStatus::Closed;
            $this->active_handoff_id = null;
            $this->closed_at = now();
            $this->closed_by_user_id = $a->actorUserId;
        });
    }
}
