<?php

namespace App\Domain\AI\Simulator\Enums;

enum SimulationRunStatus: string
{
    case Running = 'running';
    case Passed = 'passed';
    case Failed = 'failed';
}
