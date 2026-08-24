<?php

namespace App\Domain\AI\Actions;

use App\Domain\AI\Actions\Data\ActionDefinition;
use App\Domain\Network\Billing\EntitlementService;
use App\Domain\Network\Tenancy\Models\Tenant;

final class ActionRegistry
{
    private array $definitions = [];
    public function __construct(private ?EntitlementService $entitlements = null) {}
    public function register(ActionDefinition $definition): void { if (isset($this->definitions[$definition->key])) throw new \LogicException('Action key is already registered.'); $this->definitions[$definition->key] = $definition; }
    public function find(string $key): ?ActionDefinition { return $this->definitions[$key] ?? null; }
    public function all(): array { return $this->definitions; }
    public function availableFor(Tenant $tenant): array { return array_filter($this->definitions, fn (ActionDefinition $definition) => $this->isAvailable($definition, $tenant)); }
    public function isAvailable(ActionDefinition $definition, Tenant $tenant): bool { return $definition->requiredEntitlements === [] || ($this->entitlements !== null && collect($definition->requiredEntitlements)->every(fn (string $code) => $this->entitlements->has($tenant, $code))); }
}
