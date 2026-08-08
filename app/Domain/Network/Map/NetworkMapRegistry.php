<?php

namespace App\Domain\Network\Map;

use Illuminate\Support\Facades\Route;
use InvalidArgumentException;

final class NetworkMapRegistry
{
    private array $map;

    public function __construct(?array $map = null)
    {
        $this->map = $map ?? (array) config('zigo_network_map');
        $this->validate();
    }

    public function nodes(): array { return $this->map['nodes']; }
    public function connections(): array { return $this->map['connections']; }
    public function statuses(): array { return $this->map['statuses']; }
    public function grouped(): array { return collect($this->nodes())->groupBy('group')->all(); }
    public function counts(): array
    {
        $counts = array_fill_keys(array_keys($this->statuses()), 0);
        foreach ($this->nodes() as $node) $counts[$node['implementation_status']]++;
        return $counts;
    }
    public function node(string $code): ?array
    {
        return collect($this->nodes())->firstWhere('code', $code);
    }
    public function url(array $node): ?string
    {
        return $node['route_name'] && Route::has($node['route_name']) ? route($node['route_name']) : null;
    }
    public function viewData(): array
    {
        $nodes = collect($this->nodes())->map(function (array $node) {
            $node['resolved_url'] = $this->url($node);
            $node['connections'] = collect($this->connections())->filter(fn ($edge) => $edge['from'] === $node['code'] || $edge['to'] === $node['code'])->values()->all();
            return $node;
        })->all();
        return ['nodes'=>$nodes,'groups'=>collect($nodes)->groupBy('group')->all(),'connections'=>$this->connections(),'statuses'=>$this->statuses(),'statusCounts'=>$this->counts()];
    }
    public function validate(): void
    {
        $statuses = array_keys($this->map['statuses'] ?? []);
        $connectionStatuses = $this->map['connection_statuses'] ?? [];
        $commercialCodes = $this->map['commercial_module_codes'] ?? [];
        $codes = array_column($this->map['nodes'] ?? [], 'code');
        if (count($codes) !== count(array_unique($codes))) throw new InvalidArgumentException('Duplicate network node code.');
        foreach ($this->nodes() as $node) {
            if (!in_array($node['implementation_status'], $statuses, true)) throw new InvalidArgumentException("Invalid status for {$node['code']}.");
            if ($node['commercial_module_code'] && !in_array($node['commercial_module_code'], $commercialCodes, true)) throw new InvalidArgumentException("Unknown commercial module for {$node['code']}.");
            if ($node['is_clickable'] && $node['route_name'] && !Route::has($node['route_name'])) throw new InvalidArgumentException("Unknown route {$node['route_name']} for {$node['code']}.");
        }
        foreach ($this->connections() as $edge) {
            if (!in_array($edge['from'], $codes, true) || !in_array($edge['to'], $codes, true)) throw new InvalidArgumentException('Connection references an unknown node.');
            if (!in_array($edge['status'], $connectionStatuses, true)) throw new InvalidArgumentException('Invalid connection status.');
        }
    }
}
