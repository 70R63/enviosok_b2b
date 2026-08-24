<?php

namespace Tests\Feature;

use App\Domain\AI\Simulator\Data\ReadinessReport;
use Tests\TestCase;

final class AiReadinessTest extends TestCase
{
    public function test_readiness_is_binary_and_all_checks_block(): void
    {$checks=['scenario'=>true,'run'=>false];$report=new ReadinessReport(!in_array(false,$checks,true),$checks,7);$this->assertFalse($report->ready);$this->assertSame(7,$report->simulationRunId);$this->assertFalse($report->checks['run']);}
}
