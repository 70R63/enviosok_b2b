<?php

namespace App\Http\Middleware;

use App\Domain\Network\Billing\EntitlementService;
use App\Domain\Network\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;

final class EnsureTenantEntitlement
{
    public function __construct(private TenantContext $context, private EntitlementService $entitlements) {}

    public function handle(Request $request, Closure $next, string $code)
    {
        $tenant = $this->context->tenant();
        abort_unless($tenant, 404);
        abort_unless($this->entitlements->has($tenant, strtoupper($code)), 403);
        return $next($request);
    }
}
