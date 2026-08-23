<?php

namespace App\Domain\AI\Conversations\Enums;

enum ConversationMessageStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
