<?php

namespace App\Domain\AI\Leads\Enums;

enum OutcomeType: string
{
    case ValidLead = 'valid_lead';
    case ResolvedConsultation = 'resolved_consultation';
}
