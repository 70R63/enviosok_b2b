<?php

namespace App\Http\Middleware;

use App\Domain\Network\Tenancy\TenantContext;
use App\Domain\Shipping\LastMile\DriverPresenceService;
use App\Domain\Shipping\LastMile\DriverWorkspaceService;
use Closure;
use Illuminate\Http\Request;

final class ResolveCentralDriverContext
{
    public function __construct(
        private DriverWorkspaceService $workspaces,
        private TenantContext $context,
        private DriverPresenceService $presence,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        if (! $request->user()) {
            return redirect()->route('driver.login');
        }

        $key = (string) config('zigo_driver.context_session_key');
        $profile = $this->workspaces->resolve($request->user(), $request->session()->get($key));
        if (! $profile) {
            $request->session()->forget($key);
            return redirect()->route('driver.workspaces.index');
        }

        $request->session()->put($key, $profile->uuid);
        $this->context->set($profile->tenant);
        $this->presence->heartbeat($profile);
        $request->attributes->set('driver_profile', $profile);
        $request->attributes->set('central_driver', true);

        return $next($request);
    }
}
