<?php

namespace App\Domain\AI\Tenancy;

use App\Domain\AI\Support\Exceptions\AiImmutableAttributeException;
use Illuminate\Database\Eloquent\Builder;

final class AiTenantBuilder extends Builder
{
    public function update(array $values)
    {
        $protected = $this->protectedColumns(array_keys($values));
        if ($this->getModel()->isAiAppendOnly() && $protected === []) {
            throw new AiImmutableAttributeException('Append-only AI records cannot be updated.');
        }
        if ($protected !== [] && ! $this->getModel()->allowsAiProtectedUpdate($protected)) $this->rejectProtectedColumns($protected);

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

    public function insertGetId(array $values, $sequence = null)
    {
        if (! $this->getModel()->aiInsertInProgress()) throw new AiImmutableAttributeException('Direct insertGetId is prohibited for tenant-owned AI models.');
        return $this->toBase()->insertGetId($values, $sequence);
    }

    public function increment($column, $amount = 1, array $extra = [])
    {
        if ($this->getModel()->isAiAppendOnly()) throw new AiImmutableAttributeException('Append-only AI records cannot be incremented.');
        $this->rejectProtectedColumns(array_merge([(string)$column], array_keys($extra)));
        return parent::increment($column, $amount, $extra);
    }

    public function decrement($column, $amount = 1, array $extra = [])
    {
        if ($this->getModel()->isAiAppendOnly()) throw new AiImmutableAttributeException('Append-only AI records cannot be decremented.');
        $this->rejectProtectedColumns(array_merge([(string)$column], array_keys($extra)));
        return parent::decrement($column, $amount, $extra);
    }

    public function delete()
    {
        if ($this->getModel()->isAiAppendOnly()) throw new AiImmutableAttributeException('Append-only AI records cannot be deleted.');
        return parent::delete();
    }

    public function forceDelete(): never
    {
        throw new AiImmutableAttributeException('Force delete is prohibited for tenant-owned AI models.');
    }

    public function truncate(): never
    {
        throw new AiImmutableAttributeException('Truncate is prohibited for tenant-owned AI models.');
    }

    private function protectedColumns(array $columns): array
    {
        return array_values(array_intersect($this->normalizeColumns($columns), $this->getModel()->immutableAiAttributes()));
    }

    private function rejectProtectedColumns(array $columns): void
    {
        $protected = $this->getModel()->immutableAiAttributes();

        foreach ($this->normalizeColumns($columns) as $column) {
            $column = str_contains((string) $column, '.')
                ? substr((string) $column, strrpos((string) $column, '.') + 1)
                : (string) $column;

            if (in_array($column, $protected, true)) {
                throw new AiImmutableAttributeException("AI model attribute {$column} is immutable.");
            }
        }
    }

    private function normalizeColumns(array $columns): array
    {
        return array_map(static function($column):string{$column=(string)$column;return str_contains($column,'.')?substr($column,strrpos($column,'.')+1):$column;},$columns);
    }
}
