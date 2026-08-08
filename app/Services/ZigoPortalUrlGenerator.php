<?php

namespace App\Services;

use Illuminate\Support\Facades\Route;

final class ZigoPortalUrlGenerator
{
    public function __construct(private ZigoDomainResolver $domains) {}

    public function route(string $portal, string $routeName): ?string
    {
        if (!Route::has($routeName) || !$this->domains->supportsPortal($portal)) return null;
        if (!$this->domains->isSubdomainRoutingEnabled()) return route($routeName);

        $baseUrl = $this->domains->baseUrl($portal);
        $route = Route::getRoutes()->getByName($routeName);
        if (!$baseUrl || !$route || str_contains($route->uri(), '{')) return null;

        return rtrim($baseUrl, '/') . '/' . ltrim($route->uri(), '/');
    }
}
