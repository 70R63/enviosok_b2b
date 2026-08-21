<?php
namespace App\Domain\AI\Runtime\Contracts;
interface VectorStore
{
    public function upsert(string $namespace, array $vectors): void;
    public function search(string $namespace, array $vector, int $limit = 10): array;
}
