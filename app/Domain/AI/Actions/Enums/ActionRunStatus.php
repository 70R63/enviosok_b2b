<?php

namespace App\Domain\AI\Actions\Enums;

enum ActionRunStatus: string
{
    case Requested = 'requested';
    case AwaitingConfirmation = 'awaiting_confirmation';
    case Executing = 'executing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
