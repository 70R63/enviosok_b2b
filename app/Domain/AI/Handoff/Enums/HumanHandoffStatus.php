<?php

namespace App\Domain\AI\Handoff\Enums;

enum HumanHandoffStatus: string
{
    case Requested = 'requested';
    case Active = 'active';
    case Released = 'released';
    case Closed = 'closed';
}
