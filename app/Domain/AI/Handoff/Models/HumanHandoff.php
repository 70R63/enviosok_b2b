<?php

namespace App\Domain\AI\Handoff\Models;

use App\Domain\AI\Agents\Data\AuthorizedAiLifecycleActor;
use App\Domain\AI\Conversations\Models\Conversation;
use App\Domain\AI\Conversations\Models\ConversationMessage;
use App\Domain\AI\Handoff\Enums\HumanHandoffStatus;
use App\Domain\AI\Runtime\Models\RuntimeRun;
use App\Domain\AI\Tenancy\AiTenantModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class HumanHandoff extends AiTenantModel
{
    protected $table = 'ai_human_handoffs';

    protected $guarded = ['*'];

    protected $casts = ['status' => HumanHandoffStatus::class, 'requested_at' => 'datetime', 'assigned_at' => 'datetime', 'released_at' => 'datetime', 'closed_at' => 'datetime'];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected function immutableIdentityAttributes(): array
    {
        return ['uuid', 'conversation_id', 'requested_by_message_id', 'runtime_run_id', 'status', 'assigned_user_id', 'requested_at', 'assigned_at', 'released_at', 'closed_at', 'reason_code', 'safe_reason'];
    }

    protected static function booted(): void
    {
        parent::booted();
        self::creating(fn (self $h) => $h->uuid ??= (string) Str::uuid());
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function requestedByMessage(): BelongsTo
    {
        return $this->belongsTo(ConversationMessage::class, 'requested_by_message_id');
    }

    public function runtimeRun(): BelongsTo
    {
        return $this->belongsTo(RuntimeRun::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function take(AuthorizedAiLifecycleActor $a): void
    {
        if ($this->status !== HumanHandoffStatus::Requested) {
            throw new \DomainException('Handoff is no longer available.');
        } $this->persistNamedLifecycle(['status', 'assigned_user_id', 'assigned_at'], function () use ($a) {
            $this->status = HumanHandoffStatus::Active;
            $this->assigned_user_id = $a->actorUserId;
            $this->assigned_at = now();
        });
    }

    public function release(AuthorizedAiLifecycleActor $a): void
    {
        $this->assertAssigned($a);
        $this->persistNamedLifecycle(['status', 'released_at'], function () {
            $this->status = HumanHandoffStatus::Released;
            $this->released_at = now();
        });
    }

    public function closeBy(AuthorizedAiLifecycleActor $a): void
    {
        if ($this->status === HumanHandoffStatus::Active) {
            $this->assertAssigned($a);
        } elseif ($this->status !== HumanHandoffStatus::Requested) {
            throw new \DomainException('Handoff cannot be closed from its current status.');
        } else {
            $this->assertLifecycleActor($a);
        }
        $this->persistNamedLifecycle(['status', 'closed_at'], function () {
            $this->status = HumanHandoffStatus::Closed;
            $this->closed_at = now();
        });
    }

    public function assertAssigned(AuthorizedAiLifecycleActor $a): void
    {
        if ($this->status !== HumanHandoffStatus::Active || (int) $this->assigned_user_id !== $a->actorUserId) {
            throw new \DomainException('Only the assigned human can perform this action.');
        }
    }
}
