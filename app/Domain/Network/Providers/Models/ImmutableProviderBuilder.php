<?php

namespace App\Domain\Network\Providers\Models;

use Illuminate\Database\Eloquent\Builder;

final class ImmutableProviderBuilder extends Builder
{
    public function update(array $values)
    {
        throw new \LogicException('Provider history is immutable.');
    }

    public function delete()
    {
        throw new \LogicException('Provider history is immutable.');
    }

    public function forceDelete()
    {
        throw new \LogicException('Provider history is immutable.');
    }

    public function increment($column, $amount = 1, array $extra = [])
    {
        throw new \LogicException('Provider history is immutable.');
    }

    public function decrement($column, $amount = 1, array $extra = [])
    {
        throw new \LogicException('Provider history is immutable.');
    }

    public function upsert(array $values, $uniqueBy, $update = null)
    {
        throw new \LogicException('Provider history is immutable.');
    }
}
