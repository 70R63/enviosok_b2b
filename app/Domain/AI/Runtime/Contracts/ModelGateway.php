<?php
namespace App\Domain\AI\Runtime\Contracts;
interface ModelGateway { public function generate(array $request): array; }
