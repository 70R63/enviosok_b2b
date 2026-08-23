<?php

namespace App\Domain\AI\Conversations\Models;

use App\Domain\AI\Agents\Data\AuthorizedAiLifecycleActor;
use App\Domain\AI\Conversations\Enums\ConversationMessageRole;
use App\Domain\AI\Conversations\Enums\ConversationMessageStatus;
use App\Domain\AI\Runtime\Models\RuntimeRun;
use App\Domain\AI\Tenancy\AiTenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class ConversationMessage extends AiTenantModel
{
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $table = 'ai_conversation_messages';

    protected $guarded = ['*'];

    protected $casts = ['role' => ConversationMessageRole::class, 'status' => ConversationMessageStatus::class, 'content' => 'encrypted', 'sequence' => 'integer', 'needs_handoff' => 'boolean', 'completed_at' => 'datetime', 'failed_at' => 'datetime'];

    public function isAiAppendOnly(): bool
    {
        return true;
    }

    protected function immutableIdentityAttributes(): array
    {
        return ['uuid', 'conversation_id', 'sequence', 'role', 'status', 'content', 'runtime_run_id', 'confidence', 'needs_handoff', 'handoff_reason', 'safe_error_code', 'completed_at', 'failed_at'];
    }

    protected static function booted(): void
    {
        parent::booted();
        self::creating(function (self $m) {
            $m->uuid ??= (string) Str::uuid();
            Conversation::query()->findOrFail($m->conversation_id);
            if (in_array($m->role, [ConversationMessageRole::User, ConversationMessageRole::Human], true) && ($m->status !== ConversationMessageStatus::Completed || ! is_string($m->content) || trim($m->content) === '')) {
                throw new \DomainException('User and human messages must be completed.');
            }if ($m->role === ConversationMessageRole::Assistant && ($m->status !== ConversationMessageStatus::Pending || $m->content !== null)) {
                throw new \DomainException('Assistant messages must begin pending.');
            }
        });
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function runtimeRun(): BelongsTo
    {
        return $this->belongsTo(RuntimeRun::class);
    }

    public function citations(): HasMany
    {
        return $this->hasMany(ConversationMessageCitation::class)->orderBy('rank');
    }

    public function complete(AuthorizedAiLifecycleActor $a, string $content, int $runId, string $confidence, bool $handoff, string $reason): void
    {
        $this->assertLifecycleActor($a);
        if ($this->originalAiStatus() !== ConversationMessageStatus::Pending->value || $this->role !== ConversationMessageRole::Assistant) {
            throw new \DomainException('Only a pending assistant message can complete.');
        }$this->persistNamedLifecycle(['status', 'content', 'runtime_run_id', 'confidence', 'needs_handoff', 'handoff_reason', 'completed_at'], function () use ($content, $runId, $confidence, $handoff, $reason) {
            $this->status = ConversationMessageStatus::Completed;
            $this->content = $content;
            $this->runtime_run_id = $runId;
            $this->confidence = $confidence;
            $this->needs_handoff = $handoff;
            $this->handoff_reason = $reason;
            $this->completed_at = now();
        });
    }

    public function fail(AuthorizedAiLifecycleActor $a, string $code): void
    {
        $this->assertLifecycleActor($a);
        if ($this->originalAiStatus() !== ConversationMessageStatus::Pending->value) {
            throw new \DomainException('Only a pending message can fail.');
        }$this->persistNamedLifecycle(['status', 'safe_error_code', 'failed_at'], function () use ($code) {
            $this->status = ConversationMessageStatus::Failed;
            $this->safe_error_code = $code;
            $this->failed_at = now();
        });
    }
}
