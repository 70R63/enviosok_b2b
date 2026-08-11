<?php

namespace App\Http\Middleware;

use App\Domain\Network\Channels\B2C\Models\TenantCustomerProfile;
use App\Domain\Network\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;

final class AuthenticateTenantCustomer
{
    public function handle(Request $request, Closure $next)
    {
        if (! auth()->check()) return redirect()->guest('/ingresar');
        $tenantId = app(TenantContext::class)->id();
        $profile = TenantCustomerProfile::query()->where('tenant_id', $tenantId)
            ->where('user_id', auth()->id())->where('status', 'active')->first();
        if (! $profile) abort(403);
        $request->attributes->set('customer_profile', $profile);
        return $next($request);
    }
}
