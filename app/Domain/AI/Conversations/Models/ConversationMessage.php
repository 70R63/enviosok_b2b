<?php

namespace App\Domain\AI\Conversations\Models;

use App\Domain\AI\Agents\Data\AuthorizedAiLifecycleActor;
use App\Domain\AI\Conversations\Enums\ConversationMessageRole;
use App\Domain\AI\Conversations\Enums\ConversationMessageStatus;
use App\Domain\AI\Runtime\Models\RuntimeRun;
use App\Domain\AI\Runtime\Enums\RuntimeRunStatus;
use App\Domain\AI\Conversations\Exceptions\ConversationTurnSupersededException;
use App\Domain\AI\Tenancy\AiTenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

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
        return ['uuid', 'conversation_id', 'agent_id', 'agent_version_id', 'turn_token_hash', 'sequence', 'role', 'status', 'content', 'runtime_run_id', 'confidence', 'needs_handoff', 'handoff_reason', 'safe_error_code', 'completed_at', 'failed_at'];
    }

    protected static function booted(): void
    {
        parent::booted();
        self::creating(function (self $m) {
            $m->uuid ??= (string) Str::uuid();
            $conversation = Conversation::query()->findOrFail($m->conversation_id);
            if (Schema::hasColumn($m->getTable(), 'agent_id')) {
                $m->agent_id ??= $conversation->agent_id;
                $m->agent_version_id ??= $conversation->agent_version_id;
                if ((int) $m->agent_id !== (int) $conversation->agent_id || (int) $m->agent_version_id !== (int) $conversation->agent_version_id) throw new \DomainException('Message identity does not match its conversation.');
            }
            if (in_array($m->role, [ConversationMessageRole::User, ConversationMessageRole::Human], true) && ($m->status !== ConversationMessageStatus::Completed || ! is_string($m->content) || trim($m->content) === '')) {
                throw new \DomainException('User and human messages must be completed.');
            }if ($m->role === ConversationMessageRole::Assistant && ($m->status !== ConversationMessageStatus::Pending || $m->content !== null)) {
                throw new \DomainException('Assistant messages must begin pending.');
            }
            if ($m->role === ConversationMessageRole::Assistant && Schema::hasColumn($m->getTable(), 'turn_token_hash') && ! preg_match('/^[a-f0-9]{64}$/D', (string) $m->turn_token_hash)) {
                throw new \DomainException('Pending assistant messages require durable turn ownership.');
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

    public function complete(AuthorizedAiLifecycleActor $a, string $content, int $runId, string $confidence, bool $handoff, string $reason, string $turnTokenHash, string $purpose, string $mode): void
    {
        $this->assertLifecycleActor($a);
        if ($this->originalAiStatus() !== ConversationMessageStatus::Pending->value || $this->role !== ConversationMessageRole::Assistant) {
            throw new \DomainException('Only a pending assistant message can complete.');
        }
        $this->assertTurnToken($turnTokenHash);
        $run = RuntimeRun::query()->whereKey($runId)->lockForUpdate()->firstOrFail();
        if ((int) $run->tenant_id !== (int) $this->tenant_id || (int) $run->agent_id !== (int) $this->agent_id || (int) $run->agent_version_id !== (int) $this->agent_version_id || !is_string($run->conversation_turn_token_hash) || !hash_equals($run->conversation_turn_token_hash,$turnTokenHash) || $run->purpose !== $purpose || $run->execution_mode->value !== $mode || ! in_array($run->status, [RuntimeRunStatus::Completed, RuntimeRunStatus::SkippedNoKnowledge], true)) {
            throw new \DomainException('Runtime Run is incompatible with the conversation turn.');
        }
        $this->persistNamedLifecycle(['status', 'content', 'runtime_run_id', 'confidence', 'needs_handoff', 'handoff_reason', 'completed_at'], function () use ($content, $runId, $confidence, $handoff, $reason) {
            $this->status = ConversationMessageStatus::Completed;
            $this->content = $content;
            $this->runtime_run_id = $runId;
            $this->confidence = $confidence;
            $this->needs_handoff = $handoff;
            $this->handoff_reason = $reason;
            $this->completed_at = now();
        });
    }

    public function linkSourceRuntimeRun(AuthorizedAiLifecycleActor $a, int $runId): void
    {
        $this->assertLifecycleActor($a);
        if ($this->originalAiStatus() !== ConversationMessageStatus::Completed->value || $this->role !== ConversationMessageRole::User || $this->runtime_run_id !== null) {
            throw new \DomainException('Only an unlinked completed user message can be linked to its Runtime Run.');
        }
        $run = RuntimeRun::query()->whereKey($runId)->lockForUpdate()->firstOrFail();
        $conversation = $this->conversation()->firstOrFail();
        $mode = in_array($conversation->channel, [\App\Domain\AI\Conversations\Enums\ConversationChannel::Webchat, \App\Domain\AI\Conversations\Enums\ConversationChannel::WhatsApp], true) ? 'live' : 'simulation';
        $purpose = $mode === 'live' ? 'public_webchat' : 'agent_draft_simulation';
        if ((int) $run->tenant_id !== (int) $this->tenant_id || (int) $run->agent_id !== (int) $this->agent_id || (int) $run->agent_version_id !== (int) $this->agent_version_id || ! is_string($this->turn_token_hash) || ! is_string($run->conversation_turn_token_hash) || ! hash_equals($this->turn_token_hash, $run->conversation_turn_token_hash) || $run->purpose !== $purpose || $run->execution_mode->value !== $mode || ! in_array($run->status, [RuntimeRunStatus::Completed, RuntimeRunStatus::SkippedNoKnowledge], true)) throw new \DomainException('Runtime Run is incompatible with the conversation turn.');
        $this->persistNamedLifecycle(['runtime_run_id'], function () use ($runId) {
            $this->runtime_run_id = $runId;
        });
    }

    public function fail(AuthorizedAiLifecycleActor $a, string $code, string $turnTokenHash): void
    {
        $this->assertLifecycleActor($a);
        if ($this->originalAiStatus() !== ConversationMessageStatus::Pending->value) {
            throw new \DomainException('Only a pending message can fail.');
        }
        $this->assertTurnToken($turnTokenHash);
        $this->persistNamedLifecycle(['status', 'safe_error_code', 'failed_at'], function () use ($code) {
            $this->status = ConversationMessageStatus::Failed;
            $this->safe_error_code = $code;
            $this->failed_at = now();
        });
    }

    private function assertTurnToken(string $turnTokenHash): void
    {
        if (!is_string($this->turn_token_hash) || !hash_equals($this->turn_token_hash,$turnTokenHash)) throw new ConversationTurnSupersededException;
    }
}
