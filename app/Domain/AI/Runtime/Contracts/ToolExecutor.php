<?php
namespace App\Domain\AI\Runtime\Contracts;
interface ToolExecutor { public function execute(string $tool, array $arguments, array $context = []): array; }
