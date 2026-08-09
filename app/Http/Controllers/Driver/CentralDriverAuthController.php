<?php

namespace App\Http\Controllers\Driver;

use App\Domain\Shipping\LastMile\DriverWorkspaceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class CentralDriverAuthController extends Controller
{
    public function create()
    {
        return view('tenant.driver.login', ['tenant' => null, 'centralDriver' => true]);
    }

    public function store(Request $request, DriverWorkspaceService $workspaces)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::attempt($credentials, false)) {
            return back()->withErrors(['email' => 'Las credenciales no son correctas.'])->onlyInput('email');
        }

        $available = $workspaces->availableFor($request->user());
        if ($available->isEmpty()) {
            Auth::logout();
            return back()->withErrors(['email' => 'No tienes una operación Driver activa.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        $request->session()->forget((string) config('zigo_driver.context_session_key'));
        if ($available->count() === 1) {
            $request->session()->put((string) config('zigo_driver.context_session_key'), $available->first()->uuid);
            return redirect()->intended(route('driver.dashboard'));
        }

        return redirect()->route('driver.workspaces.index');
    }

    public function destroy(Request $request)
    {
        $request->session()->forget((string) config('zigo_driver.context_session_key'));
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('driver.login');
    }
}
