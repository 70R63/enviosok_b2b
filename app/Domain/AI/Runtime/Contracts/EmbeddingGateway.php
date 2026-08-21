<?php
namespace App\Domain\AI\Runtime\Contracts;
interface EmbeddingGateway { public function embed(array $inputs): array; }
