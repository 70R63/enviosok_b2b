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
        return ['name' => $this->publicName($brand?->brand_name, $tenant->slug) ?? $this->publicName($tenant->name, $tenant->slug) ?? 'Operador logístico',
            'logo' => $this->logo($tenant, $brand?->logo_path)];
    }

    private function publicName(mixed $name, ?string $slug = null): ?string
    {
        if (! is_string($name)) return null;
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 160 || preg_match('/[\x00-\x1F\x7F]|[<>]|https?:|[a-f0-9]{8}-[a-f0-9-]{27}/i', $name)) return null;
        if ($slug && mb_strtolower($name) === mb_strtolower(trim($slug))) return null;
        return $name;
    }

    /** Read historical identity without consulting current branding. */
    public function snapshotIdentity(array $guide): array
    {
        $name = $this->publicName(data_get($guide, 'provider_identity.display_name'));
        // Legacy provider values carry no explicit public-identity authorization; never infer one from casing.
        return ['display_name' => $name ?? 'Operador logístico', 'logo_path' => data_get($guide, 'provider_identity.logo_path')];
    }

    public function snapshotLogoUrl(mixed $path): ?string
    {
        if (! is_string($path) || ! preg_match('#^tenant-branding/[a-f0-9-]{36}/guide-assets/[a-f0-9]{64}\.(png|jpg|jpeg|webp)$#i', $path)) return null;
        try {
            $disk = Storage::disk('public');
            $info = $disk->exists($path) ? @getimagesize($disk->path($path)) : false;
            return $info && in_array($info['mime'] ?? '', ['image/png', 'image/jpeg', 'image/webp'], true) ? $disk->url($path) : null;
        } catch (\Throwable) {
            return null;
        }
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
