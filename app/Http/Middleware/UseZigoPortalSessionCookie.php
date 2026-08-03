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
