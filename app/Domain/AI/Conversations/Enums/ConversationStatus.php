<?php

namespace App\Domain\AI\Conversations\Enums;

enum ConversationStatus: string
{
    case Open = 'open';
    case HandoffRequested = 'handoff_requested';
    case Closed = 'closed';
}
