<?php

namespace App\Http\Middleware;

use App\Domain\Network\Tenancy\TenantAccessService;
use App\Domain\Network\Tenancy\TenantContext;
use App\Domain\Shipping\LastMile\Models\DriverProfile;
use App\Domain\Shipping\LastMile\DriverPresenceService;
use Closure;
use Illuminate\Http\Request;

final class AuthenticateDriver
{
    public function __construct(private TenantAccessService $access, private TenantContext $context, private DriverPresenceService $presence) {}

    public function handle(Request $request, Closure $next)
    {
        if (! $request->user()) {
            return redirect()->route('tenant.driver.login');
        }
        abort_unless($this->access->hasRole('driver', $request->user()), 404);
        $profile = DriverProfile::query()->where('tenant_id', $this->context->id())->where('user_id', $request->user()->id)->where('status', 'ACTIVE')->firstOrFail();
        $this->presence->heartbeat($profile);
        $request->attributes->set('driver_profile', $profile);

        return $next($request);
    }
}
