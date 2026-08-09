<?php

namespace App\Http\Middleware;

use App\Domain\Network\Tenancy\TenantAccessService;
use Closure;
use Illuminate\Http\Request;

final class EnsureTenantAdminAccess
{
    public function __construct(private TenantAccessService $access) {}

    public function handle(Request $request, Closure $next)
    {
        abort_unless($this->access->hasRole(['owner', 'admin', 'operator', 'support', 'billing', 'viewer'], $request->user()), 403);

        return $next($request);
    }
}
