<?php

namespace App\Domain\AI\Conversations\Exceptions;

final class ConversationTurnSupersededException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('The conversation turn is no longer active.');
    }
}
