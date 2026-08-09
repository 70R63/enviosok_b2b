<?php

namespace App\Http\Middleware;

use App\Services\ZigoDomainResolver;
use Closure;
use Illuminate\Http\Request;

class UseZigoPortalSessionCookie
{
    public function __construct(
        private ZigoDomainResolver $domainResolver
    ) {
    }

    public function handle(Request $request, Closure $next)
    {
        $host = strtolower($request->getHost());
        $isolatedSurface = match ($host) {
            (string) config('zigo_driver.host') => 'driver',
            (string) config('zigo_surfaces.network.host') => 'network',
            (string) config('zigo_surfaces.payments.host') => 'payments',
            default => null,
        };
        if ($isolatedSurface !== null) {
            $baseName = (string) config('session.cookie', 'zigo_session');
            $suffix = '_' . $isolatedSurface;
            if (! str_ends_with($baseName, $suffix)) $baseName .= $suffix;
            config(['session.cookie' => $baseName, 'session.domain' => null]);
        }
        if ($this->domainResolver->currentPortal($request->getHost()) === 'devops') {
            $baseName = (string) config('session.cookie', 'zigo_session');
            if (!str_ends_with($baseName, '_devops')) {
                $baseName .= '_devops';
            }

            config([
                'session.cookie' => $baseName,
                'session.domain' => null,
            ]);
        }

        return $next($request);
    }
}
