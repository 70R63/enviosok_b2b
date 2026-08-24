<?php

namespace App\Domain\AI\Conversations\Enums;

enum ConversationChannel: string
{
    case InternalTest = 'internal_test';
    case Webchat = 'webchat';
}
