<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class NetworkAuthController extends Controller
{
    public function create()
    {
        if (Auth::user()?->hasRol('sysadmin') && (int) session('network.2fa_user_id') === (int) Auth::id()) return redirect()->route('network.launchpad');
        return view('network.auth.login');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate(['email'=>['required','email'],'password'=>['required','string']]);
        if (! Auth::attempt($credentials, false)) return back()->withErrors(['email'=>'Las credenciales no son válidas.'])->onlyInput('email');
        if (! Auth::user()?->hasRol('sysadmin')) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return back()->withErrors(['email'=>'Acceso no autorizado para ZIGO Network.'])->onlyInput('email');
        }
        $userId = (int) Auth::id();
        Auth::logout();
        $request->session()->regenerate();
        $request->session()->forget(['network.2fa_user_id', 'network.2fa_verified_at']);
        $request->session()->put('network.2fa_pending_user_id', $userId);
        $enrolled = \App\Domain\Network\Security\Models\NetworkTwoFactorAuthentication::query()
            ->where('user_id', $userId)->whereNotNull('enabled_at')->exists();
        return redirect()->route($enrolled ? 'network.two-factor.challenge' : 'network.two-factor.enroll');
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->forget(['network.2fa_pending_user_id', 'network.2fa_user_id', 'network.2fa_verified_at']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('network.login');
    }
}
