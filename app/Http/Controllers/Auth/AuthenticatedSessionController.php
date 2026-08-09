<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use Log;

use App\Models\Empresa;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     *
     * @return \Illuminate\View\View
     */
    public function create(Request $request)
    {
        if (app(\App\Domain\Network\Tenancy\TenantDomainResolver::class)->resolve($request->getHost())) {
            return app(\App\Http\Controllers\Tenant\CustomerAuthController::class)->create(app(\App\Domain\Network\Tenancy\TenantContext::class));
        }
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     *
     * @param  \App\Http\Requests\Auth\LoginRequest  $request
     * @return \Illuminate\Http\RedirectResponse
     */
public function store(LoginRequest $request)
{
    if (app(\App\Domain\Network\Tenancy\TenantDomainResolver::class)->resolve($request->getHost())) {
        return app(\App\Http\Controllers\Tenant\CustomerAuthController::class)->store($request, app(\App\Domain\Network\Tenancy\TenantContext::class));
    }
    Log::debug("store Login");

    $request->authenticate();
    $request->session()->regenerate();

    $user = auth()->user();

    if ($user->empresa_id) {
        $empresa = Empresa::find($user->empresa_id);

        if ($empresa) {
            $request->session()->put('empresa_nombre', $empresa->nombre);
        }
    }

    $adminRoles = ['sysadmin', 'admin', 'adminops', 'operaciones'];

    if ($user->roles->whereIn('slug', $adminRoles)->isNotEmpty()) {
        return redirect()->intended('/admin/incidencias');
    }

    if ($user->roles->where('slug', 'cliente')->isNotEmpty()) {
        return redirect()->intended('/b2c/dashboard');
    }

    return redirect()->intended(RouteServiceProvider::HOME);
}

    /**
     * Destroy an authenticated session.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request)
    {
        if (app(\App\Domain\Network\Tenancy\TenantDomainResolver::class)->resolve($request->getHost())) {
            return app(\App\Http\Controllers\Tenant\CustomerAuthController::class)->destroy($request);
        }
        Log::debug("destruyendo sesion");
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
