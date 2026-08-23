<?php

namespace App\Domain\AI\Leads\Enums;

enum LeadStatus: string
{
    case Detected = 'detected';
    case Verified = 'verified';
}
