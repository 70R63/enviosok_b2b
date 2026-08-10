<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

final class EnsureNetworkTwoFactor
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user() || (int) $request->session()->get('network.2fa_user_id') !== (int) $request->user()->id) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('network.login');
        }

        return $next($request);
    }
}
