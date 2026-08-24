<?php

namespace App\Domain\AI\Simulator\Data;

final readonly class ReadinessReport
{
    public function __construct(public bool $ready, public array $checks, public ?int $simulationRunId = null) {}
}
