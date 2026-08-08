<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class NetworkAuthController extends Controller
{
    public function create()
    {
        if (Auth::user()?->hasRol('sysadmin')) return redirect()->route('network.launchpad');
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
        $request->session()->regenerate();
        return redirect()->intended(route('network.launchpad'));
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('network.login');
    }
}
