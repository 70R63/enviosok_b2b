<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

final class EnsureCorporateSurfaceHost
{
    public function handle(Request $request, Closure $next)
    {
        $expected = strtolower((string) config('zigo_surfaces.corporate.host'));
        abort_unless($expected !== '' && hash_equals($expected, strtolower($request->getHost())), 404);

        return $next($request);
    }
}
