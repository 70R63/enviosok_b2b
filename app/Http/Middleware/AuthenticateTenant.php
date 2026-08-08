<?php

namespace App\Http\Middleware;

use App\Domain\Network\Tenancy\TenantAccessService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class AuthenticateTenant
{
    public function __construct(private TenantAccessService $access) {}

    public function handle(Request $request, Closure $next)
    {
        if (! Auth::check()) return redirect()->route('tenant.admin.login');
        if (! $this->access->hasMembership(Auth::user())) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('tenant.admin.login')->withErrors(['email' => 'No tienes acceso a la administración de este tenant.']);
        }
        return $next($request);
    }
}
