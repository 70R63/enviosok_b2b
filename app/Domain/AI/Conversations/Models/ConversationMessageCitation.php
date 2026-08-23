<?php

namespace App\Domain\AI\Conversations\Models;

use App\Domain\AI\Conversations\Enums\ConversationMessageRole;
use App\Domain\AI\Conversations\Enums\ConversationMessageStatus;
use App\Domain\AI\Knowledge\Models\KnowledgeChunk;
use App\Domain\AI\Tenancy\AiTenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ConversationMessageCitation extends AiTenantModel
{
    protected $table = 'ai_conversation_message_citations';

    protected $guarded = ['*'];

    protected $casts = ['rank' => 'integer'];

    public function isAiAppendOnly(): bool
    {
        return true;
    }

    protected function immutableIdentityAttributes(): array
    {
        return ['conversation_message_id', 'knowledge_chunk_id', 'label', 'rank'];
    }

    protected static function booted(): void
    {
        parent::booted();
        self::creating(function (self $m) {
            $message = ConversationMessage::query()->findOrFail($m->conversation_message_id);
            $chunk = KnowledgeChunk::query()->findOrFail($m->knowledge_chunk_id);
            if ($message->role !== ConversationMessageRole::Assistant || $message->status !== ConversationMessageStatus::Completed || (int) $message->tenant_id !== (int) $chunk->tenant_id || ! preg_match('/^K(?:10|[1-9])$/D', (string) $m->label) || $m->rank < 1 || $m->rank > 10) {
                throw new \DomainException('Invalid conversation citation.');
            }
        });
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(ConversationMessage::class, 'conversation_message_id');
    }

    public function chunk(): BelongsTo
    {
        return $this->belongsTo(KnowledgeChunk::class, 'knowledge_chunk_id');
    }
}
