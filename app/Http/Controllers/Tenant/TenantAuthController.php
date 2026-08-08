<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Network\Tenancy\TenantAccessService;
use App\Domain\Network\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class TenantAuthController extends Controller
{
    public function create(TenantContext $context, TenantAccessService $access)
    {
        if (Auth::check() && $access->hasMembership(Auth::user())) return redirect()->route('tenant.admin.dashboard');
        $tenant = $context->tenant()->load('branding');
        return view('tenant.admin.auth.login', compact('tenant'));
    }

    public function store(Request $request, TenantAccessService $access)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::attempt($credentials, false)) return back()->withErrors(['email' => 'Las credenciales no son válidas.'])->onlyInput('email');
        if (! $access->hasMembership(Auth::user())) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return back()->withErrors(['email' => 'No tienes acceso a la administración de este tenant.'])->onlyInput('email');
        }
        $request->session()->regenerate();
        return redirect()->intended(route('tenant.admin.dashboard'));
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('tenant.admin.login');
    }
}
