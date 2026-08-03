<?php

namespace App\Http\Controllers\DevOps;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DevOpsAuthController extends Controller
{
    private const ALLOWED_ROLES = [
        'sysadmin',
        'admin',
        'soporte',
    ];

    public function showLogin(Request $request): View|RedirectResponse
    {
        if (Auth::check() && $this->hasAllowedRole()) {
            return redirect()->to(route('devops.index', [], false));
        }

        if (Auth::check()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return view('devops.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $remember = (bool) ($credentials['remember'] ?? false);
        unset($credentials['remember']);

        if (!Auth::attempt($credentials, $remember)
            || !$this->hasAllowedRole()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors([
                    'email' => 'Las credenciales no son válidas para este portal.',
                ])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('devops.index', [], false));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->to(route('devops.login', [], false));
    }

    private function hasAllowedRole(): bool
    {
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        foreach (self::ALLOWED_ROLES as $role) {
            if ($user->hasRol($role)) {
                return true;
            }
        }

        return false;
    }
}
