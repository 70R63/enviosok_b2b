<?php

namespace App\Domain\AI\Conversations\Data;

use App\Domain\AI\Conversations\Models\Conversation;
use App\Domain\AI\Conversations\Models\ConversationMessage;

final readonly class ConversationTurnResult
{
    public function __construct(public Conversation $conversation, public ConversationMessage $userMessage, public ConversationMessage $assistantMessage) {}
}
