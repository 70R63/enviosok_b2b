<?php
namespace App\Domain\AI\Runtime\Contracts;
interface TraceSink { public function record(array $trace): void; }
