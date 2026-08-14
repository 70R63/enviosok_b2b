<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Network\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

final class TenantBrandingAssetController extends Controller
{
    private const ATTRIBUTES = ['logo' => 'logo_path', 'hero' => 'hero_image_path', 'favicon' => 'favicon_path'];
    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public function logo(TenantContext $context) { return $this->asset($context, 'logo'); }
    public function hero(TenantContext $context) { return $this->asset($context, 'hero'); }
    public function favicon(TenantContext $context) { return $this->asset($context, 'favicon'); }

    private function asset(TenantContext $context, string $kind)
    {
        $tenant = $context->tenant();
        abort_unless($tenant, 404);

        $branding = $tenant->branding;
        $path = $branding?->{self::ATTRIBUTES[$kind]};
        $disk = Storage::disk('public');

        $tenantPrefix = 'tenant-branding/'.$tenant->uuid.'/';
        abort_unless(is_string($path) && str_starts_with($path, $tenantPrefix) && $disk->exists($path), 404);

        $mime = $disk->mimeType($path);
        abort_unless(in_array($mime, self::IMAGE_MIMES, true), 404);

        return response()->file($disk->path($path), [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
