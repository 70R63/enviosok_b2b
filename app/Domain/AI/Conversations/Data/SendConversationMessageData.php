<?php

namespace App\Domain\AI\Conversations\Data;

use App\Domain\AI\Runtime\Data\RuntimeQuestionData;

final readonly class SendConversationMessageData
{
    private function __construct(public RuntimeQuestionData $message) {}

    public static function from(mixed $message): self
    {
        return new self(RuntimeQuestionData::from($message));
    }
}
