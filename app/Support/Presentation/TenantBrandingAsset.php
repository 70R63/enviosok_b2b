<?php

namespace App\Support\Presentation;

use App\Domain\Network\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Storage;

final class TenantBrandingAsset
{
    private const ROUTES = ['logo' => 'tenant.branding.logo', 'hero' => 'tenant.branding.hero', 'favicon' => 'tenant.branding.favicon'];

    public static function url(Tenant $tenant, string $kind, mixed $path): ?string
    {
        if (! isset(self::ROUTES[$kind]) || ! is_string($path)) return null;
        if (! str_starts_with($path, 'tenant-branding/'.$tenant->uuid.'/') || ! Storage::disk('public')->exists($path)) return null;
        if ($kind === 'favicon' && ! TenantFavicon::isValid($tenant, $path)) return null;

        return route(self::ROUTES[$kind], ['v' => substr(sha1($path), 0, 12)], false);
    }
}
