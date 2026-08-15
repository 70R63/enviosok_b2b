<?php

namespace App\Support\Presentation;

use App\Domain\Network\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class TenantFavicon
{
    private const MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public static function isValid(Tenant $tenant, mixed $path): bool
    {
        if (! is_string($path) || ! str_starts_with($path, 'tenant-branding/'.$tenant->uuid.'/')) {
            return false;
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            return false;
        }

        try {
            $image = getimagesize($disk->path($path));
        } catch (Throwable) {
            return false;
        }

        if ($image === false) {
            return false;
        }

        [$width, $height] = $image;
        $mime = $image['mime'] ?? null;

        return $width === $height
            && $width <= 512
            && $height <= 512
            && in_array($mime, self::MIMES, true);
    }
}
