<?php

namespace App\Domain\AI\Actions\Data;

final readonly class ActionResultData
{
    public function __construct(public array $data) {}
}
