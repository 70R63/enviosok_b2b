<?php

namespace App\Domain\AI\Tenancy;

use App\Domain\AI\Support\Exceptions\AiImmutableAttributeException;
use Illuminate\Database\Eloquent\Builder;

final class AiTenantBuilder extends Builder
{
    public function update(array $values)
    {
        $this->rejectProtectedColumns(array_keys($values));

        return parent::update($values);
    }

    public function insert(array $values): never
    {
        throw new AiImmutableAttributeException('Bulk inserts are prohibited for tenant-owned AI models.');
    }

    public function insertOrIgnore(array $values): never
    {
        throw new AiImmutableAttributeException('Bulk inserts are prohibited for tenant-owned AI models.');
    }

    public function insertUsing(array $columns, $query): never
    {
        throw new AiImmutableAttributeException('Bulk inserts are prohibited for tenant-owned AI models.');
    }

    public function upsert(array $values, $uniqueBy, $update = null): never
    {
        throw new AiImmutableAttributeException('Bulk upserts are prohibited for tenant-owned AI models.');
    }

    private function rejectProtectedColumns(array $columns): void
    {
        $protected = $this->getModel()->immutableAiAttributes();

        foreach ($columns as $column) {
            $column = str_contains((string) $column, '.')
                ? substr((string) $column, strrpos((string) $column, '.') + 1)
                : (string) $column;

            if (in_array($column, $protected, true)) {
                throw new AiImmutableAttributeException("AI model attribute {$column} is immutable.");
            }
        }
    }
}
