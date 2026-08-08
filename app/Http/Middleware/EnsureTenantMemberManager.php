<?php

namespace App\Http\Middleware;

use App\Domain\Network\Tenancy\TenantAccessService;
use Closure;
use Illuminate\Http\Request;

final class EnsureTenantMemberManager
{
    public function __construct(private TenantAccessService $access) {}
    public function handle(Request $request, Closure $next)
    {
        abort_unless($this->access->canManageMembers($request->user()), 403);
        return $next($request);
    }
}
