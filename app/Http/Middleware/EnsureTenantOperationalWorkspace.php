<?php

namespace App\Http\Middleware;

use App\Domain\Network\ProductShell\{TenantWorkspace, TenantWorkspaceResolver};
use App\Domain\Network\Tenancy\TenantContext;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

final class EnsureTenantOperationalWorkspace
{
    public function __construct(private TenantContext $context, private TenantWorkspaceResolver $resolver) {}

    public function handle(Request $request, Closure $next)
    {
        $tenant = $this->context->tenant();
        abort_unless($tenant, 404);

        try {
            abort_unless($this->resolver->resolve($tenant) === TenantWorkspace::ZigoPlatform, 403);
        } catch (AuthorizationException) {
            abort(403);
        }

        return $next($request);
    }
}
