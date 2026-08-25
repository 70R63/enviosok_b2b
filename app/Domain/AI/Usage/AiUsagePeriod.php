<?php
namespace App\Domain\AI\Usage;
use Carbon\CarbonImmutable;
final readonly class AiUsagePeriod{public function __construct(public CarbonImmutable$start,public CarbonImmutable$end){} }
