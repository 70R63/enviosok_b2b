<?php

namespace App\Domain\Network\Providers;

use App\Domain\Network\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/** Public names and locally stored images only; never serializes tenant models. */
final class ProviderPresentation
{
    public function identity(Tenant $tenant): array
    {
        $brand = Schema::hasTable('network_tenant_brandings') ? $tenant->branding : null;
        return ['name' => $brand?->brand_name ?: $tenant->name,
            'logo' => $this->logo($tenant, $brand?->logo_path)];
    }

    public function logo(Tenant $tenant, mixed $path): ?string
    {
        if (! is_string($path) || ! str_starts_with($path, 'tenant-branding/'.$tenant->uuid.'/')
            || str_contains($path, '..') || str_contains($path, '\\')) return null;
        try {
            $disk = Storage::disk('public');
            if (! $disk->exists($path)) return null;
            $info = @getimagesize($disk->path($path));
            return $info && in_array($info['mime'] ?? '', ['image/png', 'image/jpeg', 'image/webp'], true)
                ? $disk->url($path) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function partners(): array
    {
        if (! config('provider_network.public_partner_uuids') || ! Schema::hasTable('network_provider_connections')) return [];
        $consumer = app(ProviderConsumerResolver::class)->current();
        if (! $consumer) return [];
        return app(ProviderConnectionService::class)->authorizedConnections($consumer, 'LOCAL_SHIPPING')
            ->filter(fn ($c) => in_array($c->provider->uuid, config('provider_network.public_partner_uuids', []), true)
                && app(ProviderConnectionService::class)->authorizedLocalServices($consumer, $c->provider)->isNotEmpty())
            ->map(fn ($c) => $this->identity($c->provider))->values()->all();
    }
}
