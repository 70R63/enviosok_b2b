<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

final class ProtectDriverResponse
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store, no-cache, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        return $response;
    }
}
