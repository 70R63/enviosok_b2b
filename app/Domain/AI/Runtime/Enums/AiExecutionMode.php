<?php

namespace App\Domain\AI\Runtime\Enums;

enum AiExecutionMode: string
{
    case Live = 'live';
    case Simulation = 'simulation';
}
