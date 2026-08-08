<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

final class AuthenticateNetwork
{
    public function handle(Request $request, Closure $next)
    {
        if (! auth()->check()) {
            return redirect()->guest(route('network.login'));
        }

        return $next($request);
    }
}
