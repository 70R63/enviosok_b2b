<?php

namespace App\Domain\AI\Conversations\Enums;

enum ConversationMessageRole: string
{
    case User = 'user';
    case Assistant = 'assistant';
}
