<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Network\Tenancy\TenantAccessService;
use App\Domain\Network\Tenancy\TenantContext;
use App\Domain\Shipping\LastMile\Models\DriverProfile;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class DriverAuthController extends Controller
{
    public function create(TenantContext $context)
    {
        return view('tenant.driver.login', ['tenant' => $context->tenant()->load('branding')]);
    }

    public function store(Request $request, TenantContext $context, TenantAccessService $access)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::attempt($credentials, false) || ! $access->hasRole('driver', Auth::user()) || ! DriverProfile::where('tenant_id', $context->id())->where('user_id', Auth::id())->where('status', 'ACTIVE')->exists()) {
            Auth::logout();

            return back()->withErrors(['email' => 'No tienes acceso activo como conductor.'])->onlyInput('email');
        }
        $request->session()->regenerate();

        return redirect()->intended(route('tenant.driver.dashboard'));
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('tenant.driver.login');
    }
}
