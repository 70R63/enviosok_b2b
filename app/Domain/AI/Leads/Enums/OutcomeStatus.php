<?php

namespace App\Domain\AI\Leads\Enums;

enum OutcomeStatus: string
{
    case Detected = 'detected';
    case Verified = 'verified';
}
