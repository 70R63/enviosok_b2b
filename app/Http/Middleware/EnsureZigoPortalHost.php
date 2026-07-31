<?php

namespace App\Http\Middleware;

use App\Services\ZigoDomainResolver;
use Closure;
use Illuminate\Http\Request;

class EnsureZigoPortalHost
{
    private ZigoDomainResolver $domainResolver;

    public function __construct(ZigoDomainResolver $domainResolver)
    {
        $this->domainResolver = $domainResolver;
    }

    public function handle(Request $request, Closure $next, string $portal)
    {
        if (app()->runningInConsole()
            || !$this->domainResolver->isSubdomainRoutingEnabled()) {
            return $next($request);
        }

        abort_unless(
            $this->domainResolver->currentPortal($request->getHost()) === $portal,
            404
        );

        return $next($request);
    }
}
