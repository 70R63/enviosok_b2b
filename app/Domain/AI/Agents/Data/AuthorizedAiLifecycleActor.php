<?php

namespace App\Domain\AI\Agents\Data;

use Carbon\CarbonImmutable;

/** @internal Snapshot of a completed authorization check; never a persistent security capability. */
final readonly class AuthorizedAiLifecycleActor
{
    public function __construct(public int $tenantId, public int $actorUserId, public string $role, public CarbonImmutable $authorizedAt) {}

    public function __serialize(): array
    {
        throw new \LogicException('AI lifecycle authorization snapshots cannot be serialized.');
    }

    public function __unserialize(array $data): void
    {
        throw new \LogicException('AI lifecycle authorization snapshots cannot be unserialized.');
    }
}
